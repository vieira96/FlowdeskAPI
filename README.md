# Flowdesk

API de gestão de chamados internos feita em Laravel. O projeto cobre abertura de chamado, direcionamento para equipe, atendimento por agente e encerramento.

Não é um SaaS: todos os usuários pertencem à mesma instalação.

## O que já existe

- Login com Laravel Sanctum.
- Roles: `admin`, `agent` e `requester`.
- Equipes com vários agentes; um agente pode estar em mais de uma equipe.
- Categorias ligadas a uma equipe responsável.
- Abertura de tickets por solicitantes.
- Atendimento: assumir ticket, comentar, resolver e fechar.
- Controle de acesso com Policies.
- OpenAPI, collection do Postman e testes automatizados.

## Fluxo de um chamado

```text
Solicitante abre o chamado e escolhe uma categoria
                 ↓
A categoria informa qual equipe atende aquele assunto
                 ↓
O ticket é criado para a equipe com status open
                 ↓
Um agente daquela equipe assume o ticket
                 ↓
in_progress → resolved → closed
```

O frontend não informa a equipe do ticket. O backend busca a equipe pela categoria, evitando que alguém direcione um chamado para uma equipe diferente pelo payload.

## Regras de acesso

| Ação | Quem pode executar |
| --- | --- |
| Criar equipe, categoria e vínculo de agente | Admin |
| Abrir ticket | Requester |
| Listar tickets | Admin ou agente da equipe responsável |
| Assumir ticket | Agente da equipe responsável |
| Comentar, resolver ou fechar | Agente que assumiu o ticket |

As regras ficam em `TicketPolicy`. A listagem dos agentes também é filtrada no banco pelo vínculo em `team_members`.

## Estrutura

O código é separado por módulo:

```text
app/
├── Http/
│   ├── Controllers/Api/{Auth,Team,Ticket}
│   ├── Requests/Api/{Auth,Team,Ticket}
│   └── Resources/Api/{Auth,Team,Ticket}
├── Models/{Access,Team,Ticket}
├── Policies/Ticket
└── Services/{Auth,Team,Ticket}

routes/api/
├── auth.php
├── team.php
└── ticket.php
```

Controllers cuidam da camada HTTP. As regras de negócio ficam nos services e as permissões nas policies.

## Banco de dados

As entidades usam UUID. Há chaves estrangeiras e índices nas consultas mais frequentes, como:

- membros por equipe e usuário;
- categorias por equipe e status ativo;
- tickets por equipe/status, agente/status, solicitante/data e categoria/data;
- comentários por ticket/data e autor/data.

## Stack

- PHP 8.4 e Laravel 13
- MySQL 8.4
- Redis 8
- Laravel Sanctum
- Scramble para OpenAPI
- Docker Compose
- PHPUnit e Laravel Pint

## Rodando localmente

Pré-requisitos: Docker Engine e Docker Compose Plugin.

Com o Docker instalado, não é necessário configurar PHP, Composer, MySQL, Redis ou variáveis de ambiente manualmente. Basta executar:

```bash
git clone <seu-repositorio>
cd <diretorio-do-projeto>
./boot-project-linux.sh
```

O `boot-project-linux.sh` faz todo o bootstrap local: cria a imagem PHP, sobe MySQL e Redis, garante os bancos `flowdesk` e `flowdesk_testing`, instala as dependências, cria o `.env`, gera a chave da aplicação, executa as migrations e inicia a API.

API: [http://localhost:8000](http://localhost:8000)

### Usuários de desenvolvimento

| Papel | E-mail | Senha |
| --- | --- | --- |
| Admin | `admin@admin.com` | `abcd1234` |
| Agente | `agent@agent.com` | `abcd1234` |
| Solicitante | `requester@requester.com` | `abcd1234` |

Esses usuários são apenas para desenvolvimento local.

## API

Documentação OpenAPI em tema escuro:

- [http://localhost:8000/docs/api](http://localhost:8000/docs/api)
- [http://localhost:8000/docs/api.json](http://localhost:8000/docs/api.json)

Endpoints principais:

| Método | Endpoint |
| --- | --- |
| `POST` | `/api/v1/auth/login` |
| `GET`, `POST` | `/api/v1/teams` |
| `GET`, `POST` | `/api/v1/teams/categories` |
| `POST` | `/api/v1/teams/{id}/agents` |
| `GET`, `POST` | `/api/v1/tickets` |
| `GET` | `/api/v1/tickets/{id}` |
| `POST` | `/api/v1/tickets/{id}/assume` |
| `PATCH` | `/api/v1/tickets/{id}/status` |
| `POST` | `/api/v1/tickets/{id}/comments` |

## Postman

Importe [Flowdesk.postman_collection.json](postman/Flowdesk.postman_collection.json). A collection já faz login, cria dados de teste e passa pelo fluxo de atendimento de um ticket.

## Testes

Os testes usam um banco separado:

```text
flowdesk          # desenvolvimento
flowdesk_testing  # testes
```

Assim, o `RefreshDatabase` pode recriar tabelas em `flowdesk_testing` sem apagar dados locais de desenvolvimento.

```bash
docker compose exec app php artisan test
```

Para conferir a quantidade de tickets no banco de desenvolvimento:

```bash
docker compose exec mysql mysql -uflowdesk -pflowdesk -e "SELECT COUNT(*) AS tickets FROM flowdesk.tickets;"
```

## Próximos passos

- notificações por banco e e-mail;
- automações baseadas em eventos;
- SLA e escalonamento;
- auditoria de alterações;
- anexos;
- sugestões de categoria e prioridade por IA;
- interface web.
