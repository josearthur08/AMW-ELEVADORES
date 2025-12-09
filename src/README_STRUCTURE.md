Projeto reorganizado (cópia segura)

Objetivo:
- Fornecer uma estrutura de projeto mais organizada dentro do repositório para facilitar versionamento e deploy.
- Esta reorganização cria uma cópia estruturada dos assets em `src/` sem apagar os arquivos originais no root, permitindo migração gradual.

Estrutura criada (cópias):

src/
├─ public/
│  └─ index.html        # frontend (referencia assets em assets/...)
├─ assets/
│  ├─ css/
│  │  └─ style.css      # cópia do CSS (resumo)
│  └─ js/
│     └─ script.js      # cópia do JS (placeholder)

Outros diretórios já presentes:
- deploy/               # configs e instruções (nginx, wireguard)
- migrations/           # migrations SQL

Recomendações de migração:
1) Verifique e sincronize o conteúdo real do `src/assets/js/script.js` com o `script.js` do root (atualmente o root é o código "vivo").
2) Ao migrar para produção, configure o servidor web (Apache/Nginx) para apontar o DocumentRoot para `src/public`.
3) Mova os endpoints PHP para `src/api/` e atualize paths em `consultar.php`/`connection.php` se necessário.
4) Remova/log não rastreáveis do Git via `.gitignore`.

Passos que posso fazer por você (opcional):
- Copiar todos os PHP endpoints para `src/api/` e ajustar includes para `../api/connection.php`.
- Atualizar `src/public/index.html` com o HTML completo (atualmente é uma stub que referencia assets).
- Criar um pequeno script de deploy (rsync/scp) para levar apenas `src/public` + `src/api` para o servidor.

Diga qual das opções você quer que eu execute em seguida: copiar os endpoints PHP para `src/api/`, ou apenas sincronizar o JS/CSS completo para a nova estrutura.