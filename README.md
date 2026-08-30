# Flowdesk

API de gestão de chamados internos feita em Laravel. O projeto cobre abertura de chamado, direcionamento para equipe, atendimento por agente e encerramento, com IA para orientar o solicitante em ocorrências simples antes do atendimento humano. Não há interface web: o consumo é feito pela API, OpenAPI e collection do Postman.

## O que já existe

- Login com Laravel Sanctum.
- Roles: `admin`, `agent` e `requester`.
- Equipes com vários agentes; um agente pode estar em mais de uma equipe.
- Categorias ligadas a uma equipe responsável.
- Abertura de tickets por solicitantes.
- Atendimento: assumir ticket, comentar, resolver e fechar.
- SLA por prioridade, com prazo de primeira resposta e resolução.
- Assistente de IA com Groq e fallback local via Ollama para orientar casos simples, reduzindo chamados repetitivos sem substituir o suporte humano.
- Notificações persistidas no banco e entregues em tempo real via WebSocket.
- Controle de acesso com Policies.
- OpenAPI, collection do Postman e testes automatizados.

## Fluxo de um chamado

```text
Solicitante abre o chamado e escolhe uma categoria
                 ↓
A categoria informa qual equipe atende aquele assunto
                 ↓
O ticket é criado para a equipe com status open e passa pela triagem da IA
                 ↓
IA publica orientação? ── não → equipe é notificada e o SLA começa
                 │
                sim
                 ↓
Solicitante tenta a orientação ou clica em Solicitar ajuda humana
                 ↓
Equipe é notificada e o SLA começa
                 ↓
Um agente da equipe assume o ticket
                 ↓
in_progress → resolved → closed
```

O frontend não informa a equipe do ticket. O backend busca a equipe pela categoria, evitando que alguém direcione um chamado para uma equipe diferente pelo payload.

## Regras de acesso

| Ação | Quem pode executar |
| --- | --- |
| Listar ou criar equipe e vínculo de agente | Admin |
| Criar categoria | Admin |
| Abrir ticket | Requester |
| Listar tickets | Admin, agente da equipe responsável ou solicitante (somente os próprios) |
| Assumir ticket | Agente da equipe responsável |
| Comentar no próprio ticket | Requester |
| Comentar, resolver ou fechar | Agente que assumiu o ticket |

As regras ficam em `TicketPolicy`. A listagem dos agentes também é filtrada no banco pelo vínculo em `team_members`.

## Estrutura

O código é separado por módulo:

```text
app/
├── Http/
│   ├── Controllers/Api/{Auth,Notification,Team,Ticket}
│   ├── Requests/Api/{Auth,Team,Ticket}
│   └── Resources/Api/{Auth,Notification,Team,Ticket}
├── Models/{Access,Ai,Team,Ticket}
├── Policies/Ticket
├── Services/{Ai,Auth,Notification,Sla,Team,Ticket}
└── Notifications/Ticket

routes/api/
├── auth.php
├── notification.php
├── team.php
└── ticket.php
```

Controllers cuidam da camada HTTP. As regras de negócio ficam nos services e as permissões nas policies.

## Banco de dados

As entidades usam UUID. Há chaves estrangeiras e índices nas consultas mais frequentes, como:

- membros por equipe e usuário;
- categorias por equipe e status ativo;
- tickets por equipe/status, agente/status, solicitante/data e categoria/data;
- tickets por status e vencimento de SLA para acompanhamento operacional;
- histórico de atribuição por ticket, agente e equipe;
- eventos de escalonamento de SLA por ticket e tipo;
- comentários por ticket/data e autor/data.
- notificações por usuário, leitura e data de criação.

## Stack

- PHP 8.4 e Laravel 13
- MySQL 8.4
- Redis 8
- Laravel Sanctum
- Laravel Reverb
- Scramble para OpenAPI
- Docker Compose
- PHPUnit e Laravel Pint

## SLA

Os prazos são calculados em horas corridas quando o atendimento humano é necessário: imediatamente quando a IA não publica uma orientação ou quando o solicitante pede ajuda humana após receber um hint. A primeira resposta é registrada quando um agente assume o chamado e a resolução quando o status passa para `resolved`.

| Prioridade | Primeira resposta | Resolução |
| --- | --- | --- |
| `low` | 8 horas | 72 horas |
| `medium` | 4 horas | 24 horas |
| `high` | 1 hora | 8 horas |
| `urgent` | 30 minutos | 4 horas |

O container `flowdesk-scheduler` executa o comando `tickets:escalate-sla` a cada 5 minutos. O processamento é assíncrono e dividido por responsabilidade:

1. O comando somente envia `DispatchTicketSlaEscalationsJob` para a fila Redis.
2. Esse job busca tickets elegíveis em lotes de 100 com `chunkById` e despacha `ProcessTicketSlaEscalationJob` para cada UUID encontrado.
3. O job individual recebe apenas o UUID, consulta o ticket novamente com bloqueio de banco e carrega `team.agents` para calcular a distribuição de carga e aplicar a regra de SLA.

Isso evita consultas e notificações em massa no processo do agendador, evita dados desatualizados na fila e permite que cada ticket seja processado de forma independente. Para tickets humanos ainda sem responsável, ele aplica duas etapas na primeira resposta:

1. Com 50% do prazo consumido, envia um alerta para todos os agentes da equipe responsável.
2. Com 80% do prazo consumido (20% restante), atribui automaticamente o ticket ao agente daquela equipe com menos tickets ativos (`open` e `in_progress`). Em caso de empate, usa o UUID do agente para manter a escolha determinística.

Cada alerta e atribuição é registrado uma única vez, e a atribuição — manual ou automática — gera histórico com ticket, agente, equipe, origem e horário. Quando a atribuição é automática, o agente selecionado recebe o evento `ticket.auto_assigned` e o solicitante recebe `ticket.assumed`, ambos persistidos no banco e enviados pelo WebSocket.

Para executar a verificação manualmente:

```bash
docker compose exec app php artisan tickets:escalate-sla
```

## IA para autoatendimento

O projeto prioriza a API da Groq, usando o modelo `openai/gpt-oss-20b`, para oferecer respostas rápidas. Quando a Groq responde com limite de uso (`HTTP 429`), o sistema usa automaticamente o Ollama em Docker com `qwen3:4b`. Sem `GROQ_API_KEY` configurada, o Ollama continua sendo usado localmente.

A triagem roda em fila depois que o ticket é aberto. Apenas casos classificados como simples, com confiança mínima de 85%, recebem uma orientação identificada como `Assistente IA`; o ticket não é fechado automaticamente.

A resposta é escrita para pessoas sem conhecimento técnico: usa linguagem acolhedora, passos curtos e ações seguras. Quando uma orientação é publicada, o solicitante recebe uma notificação persistida no banco e entregue em tempo real pelo WebSocket. Isso ajuda a resolver questões rotineiras — por exemplo, uma impressora sem papel — enquanto casos complexos continuam no fluxo normal da equipe.

Quando a IA não publica uma orientação — por classificar o caso como complexo, sensível ou por não conseguir concluir a análise — todos os agentes da equipe responsável recebem uma notificação persistida e em tempo real. Quando a orientação é publicada, a equipe aguarda o solicitante clicar em **Solicitar ajuda humana**; essa ação chama `POST /api/v1/tickets/{id}/request-human-assistance` e então notifica a equipe uma única vez.

### Observabilidade da IA

Falhas da triagem são registradas no log da aplicação com UUID do ticket, provedor, modelo e tipo da exceção. Quando a Groq retorna `HTTP 429`, o log registra o limite atingido e o acionamento do fallback para Ollama. As chaves de API e o conteúdo do chamado não são incluídos nesses logs.

Chamados que mencionam senha, credenciais, token, vazamento, malware ou segurança não são enviados ao modelo e seguem para atendimento humano.

O `boot-project-linux.sh` baixa o modelo e ativa a funcionalidade em uma instalação nova. Se for necessário instalar ou restaurar o modelo manualmente:

```bash
docker compose exec ollama ollama pull qwen3:4b
```

Em seguida, altere no `.env`:

```dotenv
AI_TICKET_HINTS_ENABLED=true
GROQ_API_KEY=sua_chave_da_groq
```

Crie a chave na [Groq Console](https://console.groq.com/keys). A chave não deve ser versionada nem exposta em respostas da API.

E reinicie a API e a fila:

```bash
docker compose restart app queue
```

O Ollama está disponível apenas na máquina local em `http://localhost:11434`. Em máquinas sem GPU ele pode demorar mais, mas só é acionado quando a Groq atinge o limite ou quando uma chave Groq não foi configurada.

## Rodando localmente

Pré-requisitos: Docker Engine e Docker Compose Plugin.

Com o Docker instalado, não é necessário configurar PHP, Composer, MySQL, Redis ou variáveis de ambiente manualmente. Basta executar:

```bash
git clone <seu-repositorio>
cd <diretorio-do-projeto>
./boot-project-linux.sh
```

O `boot-project-linux.sh` faz todo o bootstrap local: cria a imagem PHP, sobe MySQL, Redis, fila, agendador e servidor WebSocket, instala as dependências, cria o `.env`, gera a chave da aplicação, executa as migrations e inicia a API.

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
| `GET`, `POST` | `/api/v1/teams` — Admin |
| `GET` | `/api/v1/teams/categories` |
| `POST` | `/api/v1/teams/categories` — Admin |
| `POST` | `/api/v1/teams/{id}/agents` |
| `GET`, `POST` | `/api/v1/tickets` |
| `GET` | `/api/v1/tickets/{id}` |
| `POST` | `/api/v1/tickets/{id}/assume` |
| `POST` | `/api/v1/tickets/{id}/request-human-assistance` |
| `PATCH` | `/api/v1/tickets/{id}/status` |
| `POST` | `/api/v1/tickets/{id}/comments` |
| `GET` | `/api/v1/notifications` |
| `PATCH` | `/api/v1/notifications/{id}/read` |

## Notificações em tempo real

Ao assumir, resolver ou fechar um ticket, o solicitante recebe uma notificação persistida na tabela `notifications`. Quando a IA não publica um hint ou quando o solicitante pede ajuda humana, os agentes da equipe responsável recebem a notificação. A API permite listar as notificações e marcá-las como lidas.

O mesmo evento é entregue em tempo real pelo Laravel Reverb, através do canal privado `App.Models.User.{userId}`. Localmente, o servidor WebSocket fica em `ws://localhost:8080`; a autenticação do canal usa o token Sanctum no endpoint `POST /api/broadcasting/auth`. O ambiente local aceita qualquer origem para permitir testes pelo Postman. Em produção, `REVERB_ALLOWED_ORIGINS` deve conter apenas os domínios autorizados.

O processamento assíncrono usa Redis e o container `flowdesk-queue`. Não há envio de e-mail para esses eventos.

Ao testar manualmente, a assinatura do canal é vinculada ao `socket_id` retornado na conexão. Se a conexão for reaberta, gere uma nova autorização em `POST /api/broadcasting/auth` antes de assinar o canal novamente.

### Teste pelo Postman

1. Faça login como solicitante e guarde o token e o UUID retornados.
2. No Postman, crie uma conexão nativa em `New > WebSocket` — não abra uma requisição HTTP.
3. Conecte em `ws://localhost:8080/app/{REVERB_APP_KEY}?protocol=7&client=postman&version=1.0&flash=false`.
4. Copie o `socket_id` recebido no evento `pusher:connection_established` e envie `POST /api/broadcasting/auth` com o token Sanctum, `socket_id` e `channel_name=private-App.Models.User.{UUID_DO_SOLICITANTE}`.
5. Envie a inscrição abaixo na conexão WebSocket usando o `auth` retornado pela API:

```json
{
  "event": "pusher:subscribe",
  "data": {
    "channel": "private-App.Models.User.{UUID_DO_SOLICITANTE}",
    "auth": "{AUTH_RETORNADO_PELA_API}"
  }
}
```

O evento `pusher_internal:subscription_succeeded` confirma a inscrição. Ao assumir, resolver ou fechar um ticket, o canal recebe a notificação `ticket.activity`.

## Postman

Importe [Flowdesk.postman_collection.json](postman/Flowdesk.postman_collection.json). A collection já faz login, cria dados de teste, passa pelo fluxo de atendimento e prepara a autorização do canal privado de notificações. A conexão WebSocket deve ser criada e salva pelo menu `New > WebSocket` do Postman, pois requests WebSocket nativos não usam o mesmo formato importável das collections HTTP.

## Testes

```bash
docker compose exec app php artisan test
```

Os testes de feature cobrem autenticação, isolamento de banco, equipes, categorias, tickets, SLA, escalonamento automático, IA, notificações e solicitação de ajuda humana.

## Próximos passos

- Registrar histórico e auditoria de alterações do chamado.
- Permitir anexos em tickets e comentários.
- Criar regras de prioridade e direcionamento automático por categoria.
- Ampliar a IA para sugerir categoria, equipe responsável e prioridade com base no texto do chamado.
