Objetivo

Fornecer um guia passo-a-passo para colocar o projeto online, com duas opções de segurança:

A) Rápido: Frontend estático no GitHub Pages + backend PHP/MySQL num VPS (HTTPS + firewall + basic auth)
B) Mais seguro (recomendado para dados sensíveis): Hospedar backend num VPS protegido por WireGuard VPN — apenas clientes com o config da VPN acessam o site

Importante: GitHub Pages serve só conteúdo estático. O seu backend PHP + MySQL NÃO será executado no Pages. O fluxo típico é:
- Colocar os arquivos estáticos (HTML, CSS, client JS) no GitHub e habilitar Pages
- Deploy do backend (PHP) e DB em um servidor web (VPS, serviço gerenciado) com HTTPS
- Proteger acesso ao backend com firewall/IP whitelist, VPN (WireGuard) ou Cloudflare Access

Checklist rápido (o que eu posso preparar aqui):
- Criar .gitignore apropriado
- Gerar exemplos de configs (nginx, WireGuard) e scripts para exportar/importar DB
- Instruções de push para GitHub e ativação do Pages

Antes de começar (responda):
1) Você tem conta no GitHub? Quer que eu crie a estrutura do repositório local e arquivos auxiliares para você subir?

R: sim

2) Você tem um VPS ou prefere que eu recomende um serviço (DigitalOcean / Hetzner / Vultr / Render)?


3) Preferência de segurança: VPN (WireGuard) ou Cloudflare Access (requere provider de identidade)?

Passos resumidos (alto nível)

1) Criar repositório no GitHub e push dos arquivos (passos abaixo)
2) Habilitar GitHub Pages para a pasta `docs/` ou branch `gh-pages` (frontend estático)
3) Provisionar VPS e instalar LEMP/LAMP com PHP e MySQL
4) Copiar backend PHP para o VPS (proteger config com credenciais em arquivo fora do webroot)
5) Mover/Importar DB (mysqldump/import)
6) Configurar TLS (Let's Encrypt) e firewall (ufw)
7) Proteger acesso: WireGuard ou Cloudflare Access + CORS e tokens de API

Comandos úteis (local, para criar repositório e push):

PowerShell (Windows) — inicie na pasta do projeto (c:\xampp\htdocs) e rode:

```powershell
# inicializar git (se ainda não)
git init
git add --all
git commit -m "Initial commit: webapp"
# crie um repositório no GitHub via CLI ou web, então conecte remote
# Exemplo com GitHub CLI (gh):
# gh repo create my-awm-site --public --source=. --remote=origin --push
# ou adicione remote manualmente e envie
git remote add origin https://github.com/SEU_USUARIO/SEU_REPO.git
git branch -M main
git push -u origin main
```

Habilitar GitHub Pages
- No repositório GitHub → Settings → Pages: escolha branch `main` e pasta `/docs` (se preferir). Copie a URL pública.
- Coloque apenas frontend estático nessa pasta ou configure build step para gerar `docs/` com os assets estáticos.

Backend no VPS (exemplo Ubuntu 22.04)

```bash
# instalar Nginx, PHP, MySQL
sudo apt update && sudo apt install -y nginx mysql-server php-fpm php-mysql
# criar DB e usuários
sudo mysql
# no prompt mysql:
# CREATE DATABASE awm; CREATE USER 'awmuser'@'localhost' IDENTIFIED BY 'senha_forte'; GRANT ALL ON awm.* TO 'awmuser'@'localhost'; FLUSH PRIVILEGES; EXIT;

# colocar os arquivos PHP no /var/www/awm
sudo mkdir -p /var/www/awm
sudo chown -R $USER:www-data /var/www/awm
# copie os arquivos para /var/www/awm (via scp ou git clone)

# configurar nginx (exemplo arquivo em deploy/nginx_example.conf)
sudo ln -s /etc/nginx/sites-available/awm /etc/nginx/sites-enabled/awm
sudo nginx -t && sudo systemctl reload nginx

# configurar TLS com Certbot
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d seusite.exemplo.com

# ativar firewall
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
```

Segurança adicional (opções)
- WireGuard: instale no VPS, aceite conexões apenas de peers autorizados; configure Nginx para escutar apenas na interface local, expondo site apenas via VPN.
- Cloudflare Access: proteja via identity provider (Google/Github SSO) e regras de aplicação.
- API tokens: crie um token secreto que o frontend envia em headers (melhor para clientes autenticados) e valide no backend.

Exportar/Importar banco
- Export:
  mysqldump -u root -p awm > awm_dump.sql
- Import no VPS:
  mysql -u awmuser -p awm < awm_dump.sql

Próximo passo que eu posso fazer agora
- Gerar arquivos auxiliares no seu diretório (nginx example, WireGuard guide, export script, .gitignore). Quer que eu gere esses arquivos aqui no projeto? Se sim, confirme seu usuário de GitHub (ou diga que fará push manualmente) e se prefere a opção VPN (WireGuard) ou Cloudflare Access.

