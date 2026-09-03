# 🏛️ Gestão Integrada - Sistema Maçônico

Um sistema de gestão web completo, seguro e responsivo projetado para administrar as rotinas de uma Loja (Tesouraria, Chancelaria, Secretaria e Hospitalaria). O sistema possui controle de acesso rigoroso, fluxos de automação via webhooks e implantação contínua.

## 🚀 Principais Funcionalidades

### Segurança & Autenticação
* **Controle de Acesso em Níveis:** Separação por módulos (Tesoureiro, Chanceler, Secretário, Hospitaleiro) e níveis de permissão (Superadmin, Administrador, Leitura).
* **Autenticação em Duas Etapas (2FA):** 
  * Suporte nativo ao **Google Authenticator**.
  * Envio de token OTP via **WhatsApp** (automatizado via n8n).
* **Segurança de Primeiro Acesso:** Fluxo obrigatório de alteração de senha provisória para usuários recém-criados, com exigência de senha forte (mínimo de 8 caracteres, maiúsculas, minúsculas, números e caracteres especiais).
* **Reset de Senha:** Geração de credenciais temporárias com envio automático via e-mail.

### Chancelaria & Eventos
* **Check-in via QR Code:** Registro de presença de Obreiros e Visitantes em sessões.
* **Travas de Segurança de Tempo:** O check-in possui validação inteligente, sendo liberado apenas na data da sessão programada e a partir das 19:00h (regras ignoradas automaticamente para administradores).
* **Controle de Visitantes:** Cadastro e busca inteligente de visitantes via CIM ou Nome.

### Automações (Integração n8n)
O sistema dispara webhooks assíncronos para o **n8n**, responsáveis por:
* Disparo de códigos 2FA no WhatsApp.
* Envio de e-mails de boas-vindas com credenciais provisórias para novos usuários.
* Envio de alertas de redefinição de senha.

---

## 🛠️ Tecnologias Utilizadas

* **Backend:** PHP 8.x
* **Banco de Dados:** MySQL / MariaDB (via PDO)
* **Frontend:** HTML5, CSS3, JavaScript (Fetch API)
* **Framework CSS:** Bootstrap 5.3
* **Componentes UI:** SweetAlert2, FontAwesome 6
* **Infraestrutura/Deploy:** Docker, Easypanel, GitHub (CI/CD)

---

## ⚙️ Instalação e Configuração (Easypanel / Docker)

Este projeto está estruturado para ser hospedado em uma VPS rodando **Easypanel**. O processo de deploy é feito via GitHub.

### 1. Preparação do Banco de Dados
1. No painel do seu Easypanel, crie um serviço **MySQL**.
2. Anote o *User*, *Password* e *Database name*. O *Host* será o nome do serviço (ex: `db-gestao`).
3. Importe o dump do banco de dados (esquema inicial) através da interface do seu banco de dados na VPS.

### 2. Variáveis de Ambiente
Na aba **Environment** da sua aplicação web no Easypanel, configure as variáveis de conexão com o banco de dados. O arquivo `config.php` está programado para consumi-las automaticamente:

```env
DB_HOST=nome_do_servico_mysql
DB_NAME=seu_banco_de_dados
DB_USER=usuario_do_banco
DB_PASS=senha_do_banco
