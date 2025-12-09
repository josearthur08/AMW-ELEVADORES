// Função para alternar abas e ativar botão visualmente
function showTab(tabName) {
    // Esconde todas as abas
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active'); // para CSS moderno
        tab.style.display = 'none'; // para compatibilidade legacy
    });

    // Remove 'active' do botão das abas
    document.querySelectorAll('.tabs button').forEach(btn => btn.classList.remove('active'));

    // Exibe aba e botão ativos
    const tab = document.getElementById(tabName);
    if (tab) {
        tab.classList.add('active');
        tab.style.display = 'block';
    }
    const btn = document.getElementById("tab" + tabName);
    if (btn) btn.classList.add('active');

    // Carregar planilhas
    if(tabName === "entrada") carregarPlanilha("entrada", "tableEntrada");
    if(tabName === "saida") carregarPlanilha("saida", "tableSaida");
    if(tabName === "saida_almoxarifado") carregarPlanilha("saida_almoxarifado", "tableSaidaAlmox");
    if(tabName === "programacao") carregarPlanilha("programacao", "tableProgramacao");
    if(tabName === "clientes") carregarClientes();
}

// Inicializa a aba "entrada" ao abrir a página
// local cache for loaded tables to support client-side filtering
const dataCache = {};

function initFilters(){
    document.querySelectorAll('.filter-bar').forEach(bar => {
        const table = bar.dataset.table;
        if(!table) return;
        const text = document.getElementById(table + '_filter_text');
        const date = document.getElementById(table + '_filter_date');
        const by = document.getElementById(table + '_filter_by');
        const func = document.getElementById(table + '_filter_func');
        // wire events
        [text, date, func].forEach(el=>{ if(el) el.addEventListener('input', ()=>{
            // if data present in cache, render from cache; otherwise trigger a load
            if(dataCache[table]) renderCachedTable(table);
            else {
                // try to call carregarPlanilha for known mapping
                if(table === 'tableHistorico') carregarHistorico();
                else if(table === 'tableClientes') carregarClientes();
                else {
                    // deduce source name: tableEntrada -> entrada
                    const src = table.replace(/^table/,'');
                    carregarPlanilha(src, table);
                }
            }
        }); });
        if(by) by.addEventListener('change', ()=>{
            // toggle visibility
            if(by.value === 'date'){
                if(text) text.style.display = 'none';
                if(date) date.style.display = '';
            } else {
                if(text) text.style.display = '';
                if(date) date.style.display = 'none';
            }
            if(dataCache[table]) renderCachedTable(table);
        });
    });
}

function clearFilters(tableId){
    ['_filter_text','_filter_date','_filter_func','_filter_by'].forEach(suf=>{ const el=document.getElementById(tableId+suf); if(el) el.value=''; });
    // reset visibility: show text, hide date
    const txt = document.getElementById(tableId + '_filter_text'); if(txt) txt.style.display = '';
    const dt = document.getElementById(tableId + '_filter_date'); if(dt) dt.style.display = 'none';
    if(dataCache[tableId]) renderCachedTable(tableId);
}

function getFiltersForTable(tableId){
    const by = (document.getElementById(tableId + '_filter_by') || {}).value || 'text';
    const text = (document.getElementById(tableId + '_filter_text') || {}).value || '';
    const date = (document.getElementById(tableId + '_filter_date') || {}).value || '';
    const func = (document.getElementById(tableId + '_filter_func') || {}).value || '';
    return { by, text: text.trim().toLowerCase(), date, func };
}

function populateFuncSelect(tableId, rows){
    const sel = document.getElementById(tableId + '_filter_func');
    if(!sel) return;
    const vals = new Set();
    rows.forEach(r=>{
        for(const k in r){
            if(/funcionario|recebido|equipe|func/i.test(k) && r[k]) vals.add(r[k]);
        }
    });
    const prev = sel.value;
    sel.innerHTML = '<option value="">Todos</option>' + Array.from(vals).map(v=>`<option value="${escapeHtml(v)}">${escapeHtml(v)}</option>`).join('');
    if(prev) sel.value = prev;
}

function renderCachedTable(tableId){
    const lista = dataCache[tableId] || [];
    const tab = document.getElementById(tableId);
    if(!tab) return;
    if(!lista || lista.length === 0){
        // compute colspan from the table header if possible
        let colspan = 6;
        try{
            const table = tab.closest('table');
            if(table){
                const ths = table.querySelectorAll('thead th');
                if(ths && ths.length) colspan = ths.length;
            }
        }catch(e){ /* ignore */ }
        tab.innerHTML = `<tr><td colspan="${colspan}" style="text-align:center;">Nenhum registro</td></tr>`;
        return;
    }
    // helper formatters
    const fmtCurrency = (v, contextRow) => {
        if(v === null || v === undefined || v === '') return '';
        let s = String(v).trim();
        // remove currency symbols and whitespace
        s = s.replace(/[^0-9\.,]/g,'');
        if(s === '') return '';

        // if digits-only and 4+ chars, treat as integer cents (e.g. 9180 -> 91.80)
        if(/^[0-9]+$/.test(s) && s.length >= 4){
            const n = Number(s) / 100;
            return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n);
        }

        // if contains both '.' and ',' -> assume '.' thousands and ',' decimals
        if(s.indexOf('.') !== -1 && s.indexOf(',') !== -1){
            s = s.replace(/\./g,'').replace(/,/g,'.');
        } else if(s.indexOf(',') !== -1){
            // only comma -> decimal separator
            s = s.replace(/,/g,'.');
        } else if(s.indexOf('.') !== -1){
            // only dot -> could be decimal or thousands. If fractional part length === 3 assume thousands separator
            const parts = s.split('.');
            const frac = parts[parts.length-1];
            if(frac.length === 3){
                s = s.replace(/\./g,'');
            }
            // otherwise keep dot as decimal
        }

        let n = Number(s);
        if(isNaN(n)) return escapeHtml(v);

        // additional heuristic: sometimes backend stores cents as integer in string with no separators (e.g. '82620')
        // if number is large (>=1000) and ends with two zeros or original digits-only representation was length 4-6,
        // and dividing by 100 yields a value < number (obvious), prefer dividing by 100.
        try{
            const digitsOnly = String(v).replace(/[^0-9]/g,'');
            if(digitsOnly.length >= 4 && /^[0-9]+$/.test(digitsOnly)){
                const maybe = Number(digitsOnly) / 100;
                if(maybe < n * 0.5 || maybe * 10 < n){
                    n = maybe;
                }
            }
        }catch(e){/*ignore*/}

        return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n);
    };
    const isQtyKey = k => /quantidade|qtd|qty|amount/i.test(k);
    const isMoneyKey = k => /^(vu|vt|valor|preco|price|total|valor_unitario|valor_total)$/i.test(k);

    // populate funcionario select if present
    populateFuncSelect(tableId, lista);

        const filters = getFiltersForTable(tableId);
    const filtered = lista.filter(row=>{
        // mode: text or date
        if(filters.by === 'text'){
            if(filters.text){
                const textOk = Object.values(row).some(v=> (v||'').toString().toLowerCase().includes(filters.text));
                if(!textOk) return false;
            }
        } else if(filters.by === 'date'){
            if(filters.date){
                const dateKey = Object.keys(row).find(k=>/data/i.test(k));
                if(!dateKey) return false;
                const val = (row[dateKey]||'').toString().split(' ')[0];
                if(val !== filters.date) return false;
            }
        }
        // funcionario match
        if(filters.func){
            const found = Object.keys(row).some(k=>/funcionario|recebido|equipe|func/i.test(k) && (row[k]||'').toString() === filters.func);
            if(!found) return false;
        }
        return true;
    });

    // special rendering for Programação: group by month and day for readability
    if(tableId === 'tableProgramacao'){
        // build groups: { '2025-12': { '2025-12-05': [row, ...], ... }, ... }
        const groups = {};
        filtered.forEach(item => {
            // find date value in item (case-insensitive key)
            const dateKey = Object.keys(item).find(k=>/data/i.test(k)) || 'data';
            const raw = (item[dateKey]||'').toString().split(' ')[0];
            if(!raw) return; // skip items without date
            // Prefer parsing YYYY-MM-DD explicitly to avoid timezone/UTC shifts
            let year, month, day;
            const m = raw.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
            if(m){
                year = Number(m[1]); month = Number(m[2]); day = Number(m[3]);
            } else {
                // fallback: try Date parse (less reliable across timezones)
                const d = new Date(raw);
                if(isNaN(d.getTime())) return; // skip invalid dates
                year = d.getFullYear(); month = d.getMonth() + 1; day = d.getDate();
            }
            const monthKey = String(year) + '-' + String(month).padStart(2,'0');
            const dayKey = String(year) + '-' + String(month).padStart(2,'0') + '-' + String(day).padStart(2,'0');
            groups[monthKey] = groups[monthKey] || {};
            groups[monthKey][dayKey] = groups[monthKey][dayKey] || [];
            groups[monthKey][dayKey].push(item);
        });

        // build HTML with month headers, then day subheaders, then rows
        let html = '';
        const monthKeys = Object.keys(groups).sort();
        // compute colspan from header
        let colspan = 5; try{ const table = tab.closest('table'); if(table){ const ths = table.querySelectorAll('thead th'); if(ths && ths.length) colspan = ths.length; }}catch(e){}
        monthKeys.forEach(mk => {
            // pretty month label
            const [y, m] = mk.split('-');
                // month label formatted as MM/YY
                const monthLabel = String(m).padStart(2,'0') + '/' + String(y).slice(-2);
                html += `<tr class="grp-month" data-month="${mk}"><td colspan="${colspan}">${escapeHtml(monthLabel)}</td></tr>`;
            const dayKeys = Object.keys(groups[mk]).sort();
            dayKeys.forEach(dk => {
                // parse YYYY-MM-DD reliably and format using local date to avoid timezone shifts
                // day label as DD/MM/YY
                const dayLabel = formatDateShort(dk);
                html += `<tr class="grp-day" data-month="${mk}"><td colspan="${colspan}">${escapeHtml(dayLabel)}</td></tr>`;
                groups[mk][dk].forEach(l => {
                    const cols = ['obra','endereco','servico','data','equipe'];
                    let tr = '<tr>';
                    cols.forEach(col => {
                        let val = '';
                        for(const k in l){ if(k.toLowerCase() === col){ val = l[k]; break; } }
                        const classes = [];
                        if(col === 'endereco') classes.push('cell-endereco');
                        if(isQtyKey(col)) classes.push('cell-qty');
                        if(isMoneyKey(col)) classes.push('cell-money');
                        const cls = classes.length ? ` class="${classes.join(' ')}"` : '';
                        let out = val || '';
                        if(col.toLowerCase() === 'data' || /data/i.test(col)){
                            out = formatDateShort(out);
                        } else if(isMoneyKey(col)) out = fmtCurrency(out);
                        tr += `<td${cls}>${escapeHtml(out)}</td>`;
                    });
                    const rowId = l.id || l.ID || l.Id || l.programacao_id || null;
                    tr += `<td><div class="table-action"><button class="btn-delete small" onclick="deletarProgramacao(${rowId !== null ? rowId : 'null'})">Remover</button></div></td>`;
                    tr += '</tr>';
                    // mark this data row with the month so we can toggle visibility
                    // insert data-month attribute on the row
                    const marked = tr.replace('<tr>', `<tr data-month="${mk}">`);
                    html += marked;
                });
            });
        });
        if(!monthKeys.length) html = `<tr><td colspan="${colspan}" style="text-align:center;">Nenhum registro</td></tr>`;
        tab.innerHTML = html;
        return;
    }

    tab.innerHTML = '';
            filtered.forEach((l, rowIndex)=>{
        // Build table row with deterministic column order for known tables
        if(tableId === 'tableProgramacao'){
            // deterministic column order to match the table header
            const cols = ['obra','endereco','servico','data','equipe'];
            let tr = '<tr>';
            cols.forEach(col => {
                // pick value case-insensitively from the row object
                let val = '';
                for(const k in l){ if(k.toLowerCase() === col){ val = l[k]; break; } }
                const classes = [];
                if(col === 'endereco') classes.push('cell-endereco');
                if(isQtyKey(col)) classes.push('cell-qty');
                if(isMoneyKey(col)) classes.push('cell-money');
                const cls = classes.length ? ` class="${classes.join(' ')}"` : '';
                let out = val || '';
                if(col.toLowerCase() === 'data' || /data/i.test(col)){
                    out = formatDateShort(out);
                } else if(isMoneyKey(col)) out = fmtCurrency(out);
                tr += `<td${cls}>${escapeHtml(out)}</td>`;
            });
            tr += `<td><div class="table-action"><button class="btn-delete small" onclick="deletarProgramacao(${l.id})">Remover</button></div></td>`;
            tr += '</tr>';
            tab.innerHTML += tr;
        } else if(tableId === 'tableEntrada'){
            // fixed column order to match header
            const cols = ['nome','empresa','quantidade','vu','vt','data','horario','recebido_por'];
            let tr = '<tr>';
            cols.forEach(col => {
                const classes = [];
                if(isQtyKey(col)) classes.push('cell-qty');
                if(isMoneyKey(col)) classes.push('cell-money');
                const cls = classes.length ? ` class="${classes.join(' ')}"` : '';
                let val = l[col] || '';
                if(col.toLowerCase() === 'data' || /data/i.test(col)){
                    val = formatDateShort(val);
                } else if(isMoneyKey(col)) val = fmtCurrency(val);
                tr += `<td${cls}>${escapeHtml(val)}</td>`;
            });
            // try several possible id field names
            const rowId = l.id || l.ID || l.Id || l.entry_id || l.entrada_id || l['id_entrada'] || null;
            tr += `<td><div class="table-action"><button class="btn-delete small" onclick="deletarEntrada(${rowId !== null ? rowId : 'null'})">Remover</button></div></td>`;
            tr += '</tr>';
            tab.innerHTML += tr;
        } else if(tableId === 'tableHistorico'){
            // deterministic column order for historico: obra, endereco, data, servico, equipe
            const cols = ['obra','endereco','data','servico','equipe'];
            let tr = '<tr>';
            cols.forEach(col => {
                let val = '';
                for(const k in l){ if(k.toLowerCase() === col){ val = l[k]; break; } }
                const classes = [];
                if(col === 'endereco') classes.push('cell-endereco');
                if(isQtyKey(col)) classes.push('cell-qty');
                if(isMoneyKey(col)) classes.push('cell-money');
                const cls = classes.length ? ` class="${classes.join(' ')}"` : '';
                let out = val || '';
                if(col.toLowerCase() === 'data' || /data/i.test(col)){
                    out = formatDateShort(out);
                } else if(isMoneyKey(col)) out = fmtCurrency(out);
                tr += `<td${cls}>${escapeHtml(out)}</td>`;
            });
            const rowId = l.id || l.ID || l.Id || l.historico_id || l.id_hist || null;
            tr += `<td><div class="table-action">` +
                `<button id="sendHistBtn_${rowIndex}" class="btn-send small" onclick="enviarHistoricoParaProgramacao(${rowIndex})">Enviar → Programação</button>` +
                `<button class="btn-delete small" onclick="deletarHistorico(${rowId !== null ? rowId : 'null'})">Remover</button>` +
                `</div></td>`;
            tr += '</tr>';
            tab.innerHTML += tr;

        } else {
            let tr = '<tr>';
            for(let campo in l){ if(/^(id)$/i.test(campo)) continue; tr += `<td>${escapeHtml(l[campo])}</td>`; }
            tr += '</tr>';
            tab.innerHTML += tr;
        }
    });
}

function deletarProgramacao(id){
    if(!confirm('Remover este item de programação?')) return;
    const fd = new FormData(); fd.append('id', id);
    fetch('excluir_programacao.php', { method: 'POST', body: fd })
    .then(async r => {
        if(r.ok){
            carregarPlanilha('programacao','tableProgramacao');
        } else {
            const t = await r.text(); console.error('Erro excluir_programacao:', t); alert('Erro ao excluir programação. Veja console.');
        }
    }).catch(err=>{ console.error(err); alert('Erro ao conectar com o servidor.'); });
}

function deletarHistorico(id){
    if(!id) return alert('ID inválido para remoção');
    if(!confirm('Remover este registro do histórico?')) return;
    const fd = new FormData(); fd.append('id', id);
    fetch('excluir_historico.php', { method: 'POST', body: fd })
    .then(async r => {
        const text = await r.text();
        let json = null; try{ json = JSON.parse(text); }catch(e){}
        if(r.ok && json && json.ok){
            carregarHistorico();
        } else {
            console.error('Erro excluir_historico:', r.status, text, json);
            alert('Erro ao remover histórico. Veja console/Network para detalhes.');
        }
    }).catch(err=>{ console.error(err); alert('Erro ao conectar com o servidor.'); });
}

// Envia um item do histórico para a programação
function enviarHistoricoParaProgramacao(index){
    const lista = dataCache['tableHistorico'] || [];
    const btn = document.getElementById('sendHistBtn_' + index);
    if(!lista[index]) return alert('Registro não encontrado para envio.');
    const item = lista[index];

    // build payload using common field names (case-insensitive lookup)
    const pick = (obj, name) => {
        const k = Object.keys(obj).find(x=>x.toLowerCase()===name.toLowerCase());
        return k ? obj[k] : '';
    };
    const dados = new FormData();
    dados.append('obra', pick(item, 'obra') || '');
    dados.append('endereco', pick(item, 'endereco') || '');
    dados.append('servico', pick(item, 'servico') || '');
    // prefer a date-only value
    let dt = pick(item, 'data') || '';
    if(typeof dt === 'string') dt = dt.split(' ')[0];
    dados.append('data', dt);
    dados.append('equipe', pick(item, 'equipe') || '');

    if(btn){ btn.disabled = true; btn.dataset.orig = btn.innerText; btn.innerText = 'Enviando...'; }

    fetch('salvar_programacao.php', { method: 'POST', body: dados })
    .then(async r=>{
        const text = await r.text().catch(()=>'');
        let json = null; try{ json = JSON.parse(text); }catch(e){}
        if(r.ok && json && (json.ok === true || json.success === true)){
            // refresh programacao table
            carregarPlanilha('programacao','tableProgramacao');
            if(btn){ btn.innerText = 'Enviado'; btn.disabled = true; }
        } else {
            console.error('Erro enviarHistoricoParaProgramacao:', r.status, text, json);
            if(btn){ btn.disabled = false; btn.innerText = btn.dataset.orig || 'Enviar → Programação'; }
            alert('Erro ao enviar para programação. Veja console/Network para detalhes.');
        }
    }).catch(err=>{
        console.error('Fetch error enviarHistoricoParaProgramacao:', err);
        if(btn){ btn.disabled = false; btn.innerText = btn.dataset.orig || 'Enviar → Programação'; }
        alert('Erro ao conectar com o servidor.');
    });
}

// Parse text pasted by user for Programação and fill the fields
// Bulk-parse helper removed per user request
function parseProgramacaoText(text){
    return null;
}

// Bulk paste feature removed per user request; keep stub to avoid errors
function preencherProgramacaoFromText(){
    alert('Funcionalidade de colar texto removida. Preencha os campos manualmente.');
}

function deletarEntrada(id){
    if(!confirm('Remover este registro de entrada?')) return;
    const fd = new FormData(); fd.append('id', id);
    fetch('excluir_entrada.php', { method: 'POST', body: fd })
    .then(async r => {
        const text = await r.text();
        let json = null;
        try { json = JSON.parse(text); } catch(e) { }
        if(r.ok && json && json.ok){
            carregarPlanilha('entrada','tableEntrada');
        } else {
            console.error('Erro excluir_entrada:', r.status, text, json);
            alert('Erro ao remover entrada. Veja console/Network para detalhes.');
        }
    }).catch(err=>{ console.error(err); alert('Erro ao conectar com o servidor.'); });
}

function renderCachedClients(){
    const lista = dataCache['tableClientes'] || [];
    const tab = document.getElementById('tableClientes'); if(!tab) return;
    const filters = getFiltersForTable('tableClientes');
    const filtered = lista.filter(c => {
        if(!filters.text) return true;
        return (c.nome||'').toString().toLowerCase().includes(filters.text);
    });
    tab.innerHTML = '';
    filtered.forEach(c=>{
        const safeName = (c.nome||'').replace(/'/g,"&#39;");
        tab.innerHTML+=`<tr>
                <td>${escapeHtml(c.nome)}</td>
                <td>
                    <div class="table-action">
                        <button class="btn-edit" onclick="abrirCliente(${c.id},'${safeName}')">Abrir</button>
                        <button class="btn-delete" onclick="deletarCliente(${c.id})">Remover</button>
                    </div>
                </td>
            </tr>`;
    });
}

window.addEventListener('DOMContentLoaded', () => { initFilters(); showTab('entrada'); });

// Toggle collapse for programacao month groups by clicking month header
document.addEventListener('click', function(e){
    const monthRow = e.target.closest && e.target.closest('tr.grp-month');
    if(!monthRow) return;
    const mk = monthRow.getAttribute('data-month');
    if(!mk) return;
    const collapsed = monthRow.classList.toggle('collapsed');
    // toggle visibility of all rows tagged with this month
    const rows = Array.from(document.querySelectorAll(`tr[data-month="${mk}"]`));
    rows.forEach(r=>{ if(r.classList.contains('grp-month')) return; r.style.display = collapsed ? 'none' : ''; });
});

// ---------- ENTRADA ----------
function enviarEntrada() {
    let dados = {
        nome: document.getElementById("ent_nome").value,
        empresa: document.getElementById("ent_empresa").value,
        quantidade: document.getElementById("ent_qtd").value,
        vu: document.getElementById("ent_vu").value,
        vt: document.getElementById("ent_vt").value,
        data: document.getElementById("ent_data").value,
        horario: document.getElementById("ent_horario").value,
        recebido_por: document.getElementById("ent_recebido").value
    };
    fetch("salvar_entrada.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(dados)
    })
    .then(async response => {
        const text = await response.text().catch(()=>'');
        let json = null;
        try { json = JSON.parse(text); } catch(e){ }
        if(response.ok && json && json.ok){
            // Limpa inputs
            document.getElementById("ent_nome").value = "";
            document.getElementById("ent_empresa").value = "";
            document.getElementById("ent_qtd").value = "";
            document.getElementById("ent_vu").value = "";
            document.getElementById("ent_vt").value = "";
            document.getElementById("ent_data").value = "";
            document.getElementById("ent_horario").value = "";
            document.getElementById("ent_recebido").value = "";
            carregarPlanilha("entrada","tableEntrada");
            console.log('Entrada salva, id=', json.id);
        } else {
            console.error('Erro salvar_entrada:', response.status, text, json);
            const msg = (json && json.error) ? (json.error + (json.details ? ': ' + json.details : '')) : text || 'Resposta inesperada do servidor.';
            alert('Erro ao salvar entrada: ' + msg + '\nVerifique o console (Network/Response) para mais detalhes.');
        }
    }).catch(err=>{
        console.error('Fetch error salvar_entrada:', err);
        alert('Erro ao conectar com o servidor.');
    });
}

// ---------- SAÍDA ----------
function enviarSaida() {
    let dados = {
        nome: document.getElementById("sai_nome").value,
        obra: document.getElementById("sai_obra").value,
        equipe: document.getElementById("sai_equipe").value,
        data: document.getElementById("sai_data").value
    };
    fetch("salvar_saida.php", {
        method:"POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(dados)
    })
    .then(()=> {
        document.getElementById("sai_nome").value = "";
        document.getElementById("sai_obra").value = "";
        document.getElementById("sai_equipe").value = "";
        document.getElementById("sai_data").value = "";
        carregarPlanilha("saida","tableSaida");
    });
}

// ---------- CLIENTES ----------
let clienteAtual = null;

function addCliente() {
    const fd = new FormData();
    fd.append("nome", document.getElementById("cli_nome").value);
    fetch("salvar_cliente.php",{ method:"POST", body: fd })
    .then(()=> {
        document.getElementById("cli_nome").value="";
        carregarClientes();
    });
}

function carregarClientes() {
    fetch("consultar.php?tabela=clientes")
    .then(r=>r.json())
    .then(lista=>{
        dataCache['tableClientes'] = lista || [];
        renderCachedClients();
    });
}

function abrirCliente(id,nome){
    clienteAtual=id;
    document.getElementById("cli_titulo").innerText=nome;
    document.getElementById("clienteDetalhes").style.display="block";
    carregarHistorico();
}

function addRelatorio(){
    if(clienteAtual === null) return alert("Abra um cliente antes!");

    const obra = (document.getElementById('rel_obra').value || '').trim();
    const endereco = document.getElementById('rel_endereco') ? (document.getElementById('rel_endereco').value || '').trim() : '';
    const dataRel = (document.getElementById('rel_data').value || '').trim();
    const servico = (document.getElementById('rel_servico').value || '').trim();
    const equipe = (document.getElementById('rel_equipe').value || '').trim();

    if(!obra || !dataRel || !servico || !equipe){
        return alert('Preencha obra, data, serviço e equipe antes de salvar o relatório.');
    }

    const fd = new FormData();
    fd.append('cliente_id', clienteAtual);
    fd.append('obra', obra);
    fd.append('endereco', endereco);
    fd.append('data', dataRel);
    fd.append('servico', servico);
    fd.append('equipe', equipe);

    fetch('salvar_historico.php', { method: 'POST', body: fd })
    .then(async r => {
        const text = await r.text().catch(()=>'');
        let json = null;
        try{ json = JSON.parse(text); }catch(e){}
        if(r.ok && json && (json.ok === true || json.success === true)){
            document.getElementById('rel_obra').value='';
            if(document.getElementById('rel_endereco')) document.getElementById('rel_endereco').value='';
            document.getElementById('rel_data').value='';
            document.getElementById('rel_servico').value='';
            document.getElementById('rel_equipe').value='';
            carregarHistorico();
            // success feedback handled inline (no blocking alert)
        } else {
            console.error('salvar_historico failed', r.status, text, json);
            alert('Erro ao salvar relatório. Veja console/Network para detalhes. Resposta: ' + (text || JSON.stringify(json)));
        }
    }).catch(err=>{ console.error('Fetch error salvar_historico:', err); alert('Erro de conexão com o servidor.'); });
}

// ---------- SAÍDA ALMOXARIFADO ----------
function enviarSaidaAlmox(){
    const dados = {
        produto: document.getElementById('alm_produto').value,
        quantidade: document.getElementById('alm_qtd').value,
        funcionario: document.getElementById('alm_func').value,
        data_saida: document.getElementById('alm_data_saida').value
    };
    if(!dados.produto || !dados.quantidade || !dados.funcionario || !dados.data_saida) return alert('Preencha todos os campos da saída do almoxarifado!');
    console.log('Enviando saida almox payload:', dados);
    fetch('salvar_saida_almox.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(dados)
    }).then(async r => {
        if(r.ok){
            document.getElementById('alm_produto').value=''; document.getElementById('alm_qtd').value=''; document.getElementById('alm_func').value=''; document.getElementById('alm_data_saida').value='';
            carregarPlanilha('saida_almoxarifado','tableSaidaAlmox');
        } else {
            const t = await r.text(); console.error('Erro salvar_saida_almox:', t); alert('Erro ao salvar saída do almoxarifado. Veja console.');
        }
    }).catch(err=>{ console.error(err); alert('Erro ao conectar com o servidor.'); });
}

function deletarCliente(id){
    if(!confirm('Remover este cliente e todo o histórico?')) return;
    const fd = new FormData(); fd.append('id', id);
    fetch('excluir_cliente.php', { method: 'POST', body: fd })
    .then(async r => {
        if(r.ok){
            carregarClientes();
            // fechar detalhes do cliente se era o que estava aberto
            if(clienteAtual === id){
                clienteAtual = null;
                document.getElementById('clienteDetalhes').style.display = 'none';
            }
        } else {
            const t = await r.text(); console.error('Erro excluir_cliente:', t);
            alert('Erro ao excluir cliente. Veja console.');
        }
    }).catch(err=>{ console.error(err); alert('Erro ao excluir cliente.'); });
}

function carregarHistorico(){
    if(!clienteAtual) return;
    fetch('consultar.php?tabela=historico_cliente&cliente=' + clienteAtual)
    .then(r => r.json())
    .then(lista => {
        dataCache['tableHistorico'] = lista || [];
        renderCachedTable('tableHistorico');
    }).catch(err => { console.error('carregarHistorico error', err); alert('Erro ao carregar histórico. Veja console.'); });
}

// ---------- PROGRAMAÇÃO ----------
function addProgramacao(){
    let dados={
        obra: document.getElementById("prog_obra").value,
        endereco: (document.getElementById("prog_endereco") ? document.getElementById("prog_endereco").value : ''),
        servico: document.getElementById("prog_servico").value,
        data: document.getElementById("prog_data").value,
        equipe: document.getElementById("prog_equipe").value
    };
    fetch("salvar_programacao.php",{ 
        method:"POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(dados)
    })
    .then(async (r)=>{
        const text = await r.text().catch(()=>"");
        let json = null;
        try{ json = JSON.parse(text); }catch(e){}
        if(r.ok && json && (json.ok === true || json.success === true)){
            document.getElementById("prog_obra").value = "";
            if(document.getElementById("prog_endereco")) document.getElementById("prog_endereco").value = "";
            document.getElementById("prog_servico").value = "";
            document.getElementById("prog_data").value = "";
            document.getElementById("prog_equipe").value = "";
            carregarPlanilha("programacao","tableProgramacao");
        } else {
            console.error('Erro salvar_programacao:', r.status, text, json);
            alert('Erro ao salvar programação. Veja console/Network para detalhes.');
        }
    }).catch(err=>{ console.error('Fetch error salvar_programacao:', err); alert('Erro ao conectar com o servidor.'); });
}

// ---------- PLANILHAS ----------
function carregarPlanilha(tabela,idTabela){
    fetch("consultar.php?tabela="+tabela)
    .then(r=>{
        return r.json().catch(e=>{ console.error('JSON parse error for tabela', tabela, e); return []; });
    })
    .then(lista=>{
        dataCache[idTabela] = lista || [];
        renderCachedTable(idTabela);
    }).catch(err=>{
        console.error('Erro ao carregar planilha', tabela, err);
        const tab=document.getElementById(idTabela); if(tab) tab.innerHTML='<tr><td colspan="5" style="text-align:center;">Erro ao carregar</td></tr>';
    });
}

// ---------- SOLICITAR MATERIAL ----------
let solicitarList = [];

function addSolicitar(){
    const nome = document.getElementById("sol_nome").value.trim();
    const qtd = document.getElementById("sol_qtd").value;
    if(!nome || !qtd) return alert("Preencha os campos!");
    solicitarList.push({ nome, qtd });
    document.getElementById("sol_nome").value = "";
    document.getElementById("sol_qtd").value = "";
    renderSolicitarTable();
}

function renderSolicitarTable(){
    const tbody = document.getElementById("tableSolicitar");
    if(!tbody) return;
    tbody.innerHTML = "";
    solicitarList.forEach((item, idx) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${idx+1}</td>
            <td>${escapeHtml(item.nome)}</td>
            <td><span class="qty-badge">${item.qtd}</span></td>
            <td><div class="table-action"><button class="btn-delete small" onclick="removeSolicitar(${idx})">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align:middle;margin-right:6px"><path d="M3 6h18" stroke="#ef4444" stroke-width="2" stroke-linecap="round"/><path d="M8 6v12a2 2 0 002 2h4a2 2 0 002-2V6" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Remover
            </button></div></td>`;
        tbody.appendChild(tr);
    });
}

function removeSolicitar(index){
    if(index >= 0 && index < solicitarList.length){
        solicitarList.splice(index,1);
        renderSolicitarTable();
    }
}

function gerarExcel(){
    // Exporta arquivo .xls (HTML table) com layout mais fiel ao modelo do usuário
    const totalRows = Math.max(12, solicitarList.length);
    const styles = `
        <style>
            table{border-collapse:collapse;font-family:Calibri,Arial,Helvetica,sans-serif;width:100%;}
            thead th{background:#ffffff;color:#000000;padding:10px 6px;border-bottom:2px solid #c8ddff;font-weight:700;text-align:left;}
            thead th.prod{width:80%;}
            thead th.qtd{width:20%;text-align:center}
            tbody td{padding:10px 6px;border:1px solid #e6f3ff}
            tbody tr:nth-child(odd) td{background:#eef6ff}
            tbody tr:nth-child(even) td{background:#ffffff}
            td.center{ text-align:center }
        </style>`;

    const header = `<table><thead><tr><th class="prod">PRODUTO</th><th class="qtd">QUANTIDADE</th></tr></thead><tbody>`;
    let body = '';
    for(let i=0;i<totalRows;i++){
        if(solicitarList[i]){
            const nome = escapeHtml(solicitarList[i].nome || '');
            const qtd = escapeHtml(String(solicitarList[i].qtd || ''));
            body += `<tr><td>${nome}</td><td class="center">${qtd}</td></tr>`;
        } else {
            body += `<tr><td>&nbsp;</td><td class="center">&nbsp;</td></tr>`;
        }
    }
    const footer = `</tbody></table>`;
    const html = `<!doctype html><html><head><meta charset="utf-8">${styles}</head><body>${header}${body}${footer}</body></html>`;

    const blob = new Blob([html], { type: 'application/vnd.ms-excel;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href = url; a.download = 'solicitacao_material_formatada.xls'; document.body.appendChild(a); a.click(); a.remove();
    URL.revokeObjectURL(url);
}

// (removed downloadModeloSolicitacao as requested)

function escapeHtml(str){
    if (str === null || str === undefined) return '';
    // If it's an object or array, stringify it safely
    if (typeof str === 'object'){
        try { str = JSON.stringify(str); } catch(e) { str = String(str); }
    }
    // Coerce non-string values (numbers, booleans) to string
    if (typeof str !== 'string') str = String(str);
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Formata data para DD/MM/YY (aceita 'YYYY-MM-DD' ou strings com datetime)
function formatDateShort(raw){
    if(!raw && raw !== 0) return '';
    // if already a Date
    if(raw instanceof Date){
        const d = raw; const day = String(d.getDate()).padStart(2,'0'); const mon = String(d.getMonth()+1).padStart(2,'0'); const yy = String(d.getFullYear()).slice(-2); return `${day}/${mon}/${yy}`;
    }
    let s = String(raw).trim();
    // extract YYYY-MM-DD at start
    const m = s.match(/(\d{4})-(\d{1,2})-(\d{1,2})/);
    if(m){
        const y = m[1], mo = m[2].padStart(2,'0'), d = m[3].padStart(2,'0');
        return `${d}/${mo}/${y.slice(-2)}`;
    }
    // fallback: try Date parse and format
    const dt = new Date(s);
    if(!isNaN(dt.getTime())){
        const day = String(dt.getDate()).padStart(2,'0'); const mon = String(dt.getMonth()+1).padStart(2,'0'); const yy = String(dt.getFullYear()).slice(-2);
        return `${day}/${mon}/${yy}`;
    }
    return s;
}

// diagnostic helper removed per user request