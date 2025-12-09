WireGuard (opção VPN) — resumo de passos

Objetivo: criar uma VPN simples entre máquinas autorizadas e o servidor onde o backend roda. Só quem tiver o arquivo de configuração do WireGuard conseguirá alcançar o serviço.

1) Instalar WireGuard no servidor (Ubuntu):

```bash
sudo apt update
sudo apt install -y wireguard
```

2) Gerar chaves no servidor:

```bash
wg genkey | tee server_private.key | wg pubkey > server_public.key
```

3) Criar arquivo /etc/wireguard/wg0.conf (exemplo):

```ini
[Interface]
Address = 10.10.10.1/24
ListenPort = 51820
PrivateKey = <conteudo de server_private.key>
SaveConfig = true

# Exemplo de peer (cliente) será adicionado depois

```

4) Ativar e iniciar:

```bash
sudo systemctl enable wg-quick@wg0
sudo systemctl start wg-quick@wg0
sudo wg show
```

5) Gerar chaves no cliente (Windows/Mac/Linux) e criar peer no servidor (adicionar bloco [Peer] em wg0.conf):

- No cliente:
  - `wg genkey | tee client_private.key | wg pubkey > client_public.key`

- No servidor (adicionar ao wg0.conf):

```ini
[Peer]
PublicKey = <client_public_key>
AllowedIPs = 10.10.10.2/32
```

- No cliente, configurar wg0.conf com o endpoint do servidor e a chave privada do cliente:

```ini
[Interface]
Address = 10.10.10.2/32
PrivateKey = <client_private_key>
DNS = 10.10.10.1

[Peer]
PublicKey = <server_public_key>
Endpoint = seu_ip_publico:51820
AllowedIPs = 0.0.0.0/0
PersistentKeepalive = 25
```

6) Roteamento / firewall
- No servidor permita UDP 51820 no firewall (ufw): `sudo ufw allow 51820/udp`.
- Recomendado: faça com que Nginx apenas escute em `127.0.0.1` (ou numa interface WireGuard) e roteie o tráfego via VPN.

7) Vantagem
- Máquinas sem o arquivo WireGuard não conseguem acessar o backend.
- Controle centralizado dos peers; revogue removendo o bloco [Peer] do servidor e recarregando WireGuard.

Observação: este é um resumo prático. Posso gerar arquivos de configuração completos e scripts para provisionar server/client se quiser.