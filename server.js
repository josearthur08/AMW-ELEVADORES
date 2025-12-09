const express = require("express");
const bodyParser = require("body-parser");
const sqlite3 = require("sqlite3").verbose();
const path = require("path");

const app = express();
app.use(bodyParser.json());
app.use(express.static("public"));

const db = new sqlite3.Database("./database.db");

// Criação das tabelas
db.serialize(() => {
    db.run(`CREATE TABLE IF NOT EXISTS entrada_material (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nome TEXT,
        empresa TEXT,
        valor_unit REAL,
        valor_total REAL,
        data TEXT,
        horario TEXT,
        recebido_por TEXT
    )`);

    db.run(`CREATE TABLE IF NOT EXISTS saida_material (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nome TEXT,
        obra TEXT,
        equipe TEXT,
        data TEXT
    )`);

    db.run(`CREATE TABLE IF NOT EXISTS clientes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nome TEXT
    )`);

    db.run(`CREATE TABLE IF NOT EXISTS clientes_historico (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        cliente_id INTEGER,
        data TEXT,
        servico TEXT,
        executado_por TEXT
    )`);

    db.run(`CREATE TABLE IF NOT EXISTS solicitacoes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nome TEXT,
        quantidade INTEGER
    )`);

    db.run(`CREATE TABLE IF NOT EXISTS programacao (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        obra TEXT,
        servico TEXT,
        data TEXT,
        equipe TEXT
    )`);
});

// ROTAS DE API
app.post("/entrada", (req, res) => {
    const d = req.body;
    db.run(`INSERT INTO entrada_material (nome, empresa, valor_unit, valor_total, data, horario, recebido_por)
            VALUES (?, ?, ?, ?, ?, ?, ?)`,
        [d.nome, d.empresa, d.valor_unit, d.valor_total, d.data, d.horario, d.recebido_por]);
    res.json({status: "ok"});
});

app.post("/saida", (req, res) => {
    const d = req.body;
    db.run(`INSERT INTO saida_material (nome, obra, equipe, data)
            VALUES (?, ?, ?, ?)`,
        [d.nome, d.obra, d.equipe, d.data]);
    res.json({status: "ok"});
});

app.post("/cliente", (req, res) => {
    db.run(`INSERT INTO clientes (nome) VALUES (?)`,
        [req.body.nome]);
    res.json({status: "ok"});
});

app.post("/cliente/historico", (req, res) => {
    const d = req.body;
    db.run(`INSERT INTO clientes_historico (cliente_id, data, servico, executado_por)
            VALUES (?, ?, ?, ?)`,
        [d.cliente_id, d.data, d.servico, d.executado_por]);
    res.json({status: "ok"});
});

app.post("/solicitar", (req, res) => {
    const d = req.body;
    db.run(`INSERT INTO solicitacoes (nome, quantidade) VALUES (?, ?)`,
        [d.nome, d.quantidade]);
    res.json({status: "ok"});
});

app.post("/programacao", (req, res) => {
    const d = req.body;
    db.run(`INSERT INTO programacao (obra, servico, data, equipe)
            VALUES (?, ?, ?, ?)`,
        [d.obra, d.servico, d.data, d.equipe]);
    res.json({status: "ok"});
});

app.get("/", (req, res) => {
    res.sendFile(path.join(__dirname, "public", "index.html"));
});

app.listen(3000, () => console.log("Servidor rodando em http://localhost:3000"));
