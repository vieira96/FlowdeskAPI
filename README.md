# Flowdesk

Sistema inteligente de gestão de chamados, desenvolvido com Laravel, PHP 8.4, MySQL 8.4 e Redis 8 em Docker.

O projeto será construído por módulos: autenticação e acessos, chamados, prioridades e SLA, comentários, automações, auditoria, notificações e recursos de IA. Ele não possui isolamento por empresas: todos os usuários pertencem à mesma instalação.

## Acesso inicial

Após a primeira instalação, entre pela API com o usuário administrador local:

- E-mail: `admin@admin.com`
- Senha: `abcd1234`

Também são criados usuários de demonstração: `agent@agent.com` (agente) e `requester@requester.com` (solicitante), ambos com a senha `abcd1234`.

Altere essa senha antes de expor a aplicação em qualquer ambiente público.

## Primeira instalação no Linux

É necessário ter Docker Engine e o plugin Docker Compose instalados. Depois de clonar o repositório, execute:

```bash
./boot-project-linux.sh
```

O script constrói a imagem, inicia MySQL e Redis, instala as dependências PHP, cria o `.env`, gera a chave da aplicação, roda as migrations e inicia a aplicação.

## Uso diário

```bash
docker compose up -d
```

A aplicação estará em [http://localhost:8000](http://localhost:8000).

## Documentação da API

Com a aplicação em execução, a documentação interativa OpenAPI está disponível em:

- [http://localhost:8000/docs/api](http://localhost:8000/docs/api)
- Especificação OpenAPI JSON: [http://localhost:8000/docs/api.json](http://localhost:8000/docs/api.json)

Os endpoints protegidos usam autenticação Bearer via Laravel Sanctum.

## Postman

Importe [Flowdesk.postman_collection.json](postman/Flowdesk.postman_collection.json) no Postman e execute as requisições na ordem das pastas. A collection realiza os logins, cria dados de teste e executa o fluxo completo de atendimento de um ticket.

Para executar comandos Artisan:

```bash
docker compose exec app php artisan <comando>
```

Para parar os serviços mantendo os dados:

```bash
docker compose down
```
