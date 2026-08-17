# PokéLink

Catálogo Pokémon autenticado, com busca ao vivo, favoritos por usuário e chat em tempo real.
Aplicação Laravel full-stack que sobe inteira com **um comando**.

---

## Como rodar

**Pré-requisitos:** Docker Engine 24+ e Docker Compose v2. Nada mais — nem PHP, nem MySQL, nem Redis, nem Node.

```bash
cp .env.example .env
docker compose up -d
```

Abra <http://localhost:8000>. Você será redirecionado para `/login`.

> O `cp` é conveniência: se o `.env` não existir, o entrypoint do container faz essa cópia sozinho.

O primeiro boot leva cerca de 3 a 5 minutos (download das imagens e build). Os seguintes levam segundos.

### Credenciais

Ambas as contas são **usuários comuns**. O rótulo ADMIN existe apenas para você abrir duas sessões
lado a lado e verificar que favoritos, perfil e conversas são isolados por dono — ele não concede
nenhuma tela ou permissão extra.

| Papel | E-mail | Senha |
|---|---|---|
| ADMIN | `admin@pokelink.test` | `password` |
| USER | `user@pokelink.test` | `password` |

### Endereços

| O quê | URL |
|---|---|
| Aplicação | <http://localhost:8000> |
| Health check | <http://localhost:8000/up> |
| Horizon (filas) | <http://localhost:8000/horizon> |
| Reverb (WebSocket) | `ws://localhost:8080/app/pokelink-key` |

---

## O que sobe

Seis serviços, a partir de um único `docker compose up -d`:

| Serviço | Imagem | Papel |
|---|---|---|
| `app` | `php:8.3-fpm` (build local) | PHP-FPM. **Único serviço que migra e popula o banco.** |
| `web` | `nginx:1.27-alpine` | Porta de entrada HTTP, publicada em `8000` |
| `mysql` | `mysql:8.0` | Schema e sessões |
| `redis` | `redis:7-alpine` | Cache e conexão de filas |
| `queue` | mesma imagem do `app` | Worker do Horizon |
| `reverb` | mesma imagem do `app` | Servidor WebSocket, publicado em `8080` |

`app`, `queue` e `reverb` compartilham **uma imagem** e diferem apenas no comando e na variável
`CONTAINER_ROLE`.

### Sequência de boot

```
mysql + redis (healthy)
   └─> app  ── entrypoint ──> .env → APP_KEY → storage → espera o banco
                              → migrate (+ seed no primeiro boot)
                              → storage:link → optimize
                              → marca "pronto" e sobe o php-fpm
          └─> web, queue, reverb (só depois de app estar healthy)
```

O `app` só é considerado *healthy* quando o entrypoint termina **e** o PHP-FPM aceita conexões.
`queue` e `reverb` esperam por isso, o que garante que o worker nunca sobe contra um schema
inexistente e que exatamente um serviço roda as migrations.

### Comandos úteis

```bash
docker compose logs -f app          # acompanhar o boot
docker compose exec app php artisan test
docker compose exec app php artisan tinker
docker compose exec app php artisan migrate:status
docker compose exec mysql mysql -u pokelink -ppokelink pokelink

docker compose down                 # para tudo, preserva o banco
docker compose down -v && docker compose up -d   # ambiente limpo, migrado e populado
```

Os mesmos dois comandos de boot/derrubada acima existem como scripts idempotentes —
`gates/init.sh` (cria o `.env` se faltar, sobe com `--wait` e imprime a mesma tabela de
credenciais/endpoints deste README) e `gates/down.sh` (`down --volumes --remove-orphans` mais a
remoção da imagem `pokelink-app`, para o próximo boot reconstruir do zero). Nenhum dos dois faz
parte de `gates/all.sh`, que roda só as gates de qualidade (lint, análise estática, testes etc.),
descritas no `CLAUDE.md`.

---

## Decisões técnicas

**Laravel 12, não Laravel 11.** O Laravel 11 saiu do suporte de segurança e o Composer 2.10 se
recusa a instalar qualquer release 11.x por causa de seis advisories em aberto. O Laravel 12 roda
sobre a mesma base `php:8.3-fpm`, continua em suporte, e mantém as convenções que o restante do
projeto assume. Registrado em [`docs/adr/0001-laravel-12-instead-of-laravel-11.md`](docs/adr/0001-laravel-12-instead-of-laravel-11.md).

**Imagem em três estágios, com bind mount por cima.** O estágio `assets` (`node:20`) compila o
bundle do Vite e o estágio `vendor` resolve as dependências PHP; o estágio final apenas copia os
dois. Assim o revisor não precisa de toolchain nenhum na máquina. Por cima, o código-fonte é
bind-montado e `vendor/`, `public/build/` e `storage/` são volumes nomeados sobrepostos — então
editar um arquivo PHP tem efeito imediato, sem rebuild, e os artefatos construídos na imagem
sobrevivem à montagem.

**Consequência:** alterar JS ou CSS exige rebuild (`docker compose build app`), porque não há
serviço Node na stack. Durante o desenvolvimento, o atalho é um container descartável:

```bash
docker run --rm -v "$PWD":/app -w /app -u $(id -u):$(id -g) node:20-alpine npm run build
```

**Dois marcadores, não um.** O marcador de instalação vive em `storage/app/.pokelink-installed`,
sobre o volume `pokelink_storage`: um `restart` encontra o marcador e só aplica migrations
pendentes, enquanto `down -v` destrói o volume e o próximo boot popula tudo de novo. O marcador de
prontidão vive fora de qualquer volume, no filesystem do container, e por isso nunca sobrevive a uma
recriação — é ele que o healthcheck testa. Um marcador só serviria a um dos dois propósitos.

**`FORWARD_DB_PORT`, e não `DB_PORT`, publica a porta no host.** `DB_PORT` continua sendo a porta
que a aplicação usa para falar com o serviço `mysql` na rede do Compose. Se as duas fossem a mesma
chave, mudar a porta publicada quebraria a conexão da própria aplicação. Mesma lógica para
`FORWARD_REDIS_PORT`.

**Dependências de desenvolvimento instaladas.** `php artisan test` roda dentro do container, e isso
exige Pest e Faker presentes. O artefato entregue é um ambiente local de avaliação, não uma imagem
de produção.

**`phpredis` em vez de `predis`.** Menor latência e é o cliente preferido pelo Horizon. O custo é
uma etapa de compilação na imagem, que o build multi-stage já absorve.

**Container roda com o UID do host.** Os arquivos escritos através do bind mount (`.env`, o link
`public/storage`) continuam pertencendo ao desenvolvedor, e não ao root. Se o seu usuário não for
`1000:1000`, exporte antes de subir:

```bash
UID=$(id -u) GID=$(id -g) docker compose up -d
```

**`APP_DEBUG=true` e `APP_ENV=local` no `.env.example`.** O alvo da entrega é uma stack local de
avaliação, e a saída de debug ajuda o revisor a diagnosticar. Não use este `.env.example` como base
para produção.

**Horizon aberto em `local`.** Espera-se que o revisor acompanhe o job de sincronização do catálogo
durante o primeiro boot, antes de ter motivo para logar. Em qualquer outro ambiente o dashboard
exige autenticação.

**Alpine vem junto do Livewire.** O Livewire 3 registra o Alpine globalmente, então ele não é
instalado como pacote npm separado — importá-lo de novo registraria dois Alpines na mesma página.

---

## Testes

```bash
docker compose exec app php artisan test
```

A suíte roda contra um SQLite em memória (configurado em `phpunit.xml`), uma escolha exclusiva de
teste — a aplicação em si roda sobre MySQL. Nenhum teste acessa a rede (todo acesso ao PokeAPI é
substituído por `Http::fake`).

189 casos (760 assertions) em 24 arquivos, ~15s — cobrindo autenticação, registro, shell, busca
com cache, detalhes, favoritos (incluindo idempotência e autorização cross-user), perfil, chat
(incluindo autorização de canal), sincronização do catálogo e o cliente PokeAPI (retry, timeout,
circuito, rate limit).

Além da suíte Pest, `gates/*.sh` roda uma segunda camada de verificação estática — Pint, Larastan,
PHP Insights, Deptrac, PHPCPD e `composer audit` — descrita em detalhe no `CLAUDE.md`, e um punhado
de specs Playwright de regressão visual em `tests/Browser/`.

Para rodar um grupo específico:

```bash
docker compose exec app php artisan test --filter=Favorite
```

---

## Problemas comuns

**`address already in use` ao subir.** Alguma porta do host já está ocupada. Ajuste no `.env` — não
no `docker-compose.yml`:

| Erro menciona | Chave a mudar | Padrão |
|---|---|---|
| `0.0.0.0:8000` | `APP_PORT` | `8000` |
| `0.0.0.0:3306` | `FORWARD_DB_PORT` | `3306` |
| `0.0.0.0:6379` | `FORWARD_REDIS_PORT` | `6379` |
| `0.0.0.0:8080` | `REVERB_PORT` | `8080` |

Rodar MySQL ou Redis local é a causa mais frequente. Depois de editar, `docker compose up -d` de novo.

**`banco de dados não respondeu em 60s`.** O entrypoint desiste após 30 tentativas e sai com código
diferente de zero, em vez de deixar a aplicação subir e falhar depois com um erro 500. Veja o que
houve com `docker compose logs mysql`.

**Migration falhou.** O entrypoint aborta **antes** de popular o banco e não grava o marcador de
instalação, então o próximo boot tenta de novo a partir de um estado limpo. O nome da migration que
falhou aparece em `docker compose logs app`.

**`queue` ou `reverb` não sobem.** Os dois dependem de `app` estar *healthy*. Comece por
`docker compose ps` e depois `docker compose logs app`.

**Configuração alterada no `.env` não faz efeito.** O entrypoint roda `php artisan optimize`, que
cacheia a configuração. Rode `docker compose exec app php artisan optimize:clear`, ou apenas
`docker compose restart app`.

---

## Entregue

Todas as 13 features do PRD (`docs/prd.md`) estão implementadas e cobertas por testes:

- **F01** Stack completa de 6 serviços subindo com um comando, com healthchecks, ordenação entre
  serviços, entrypoint idempotente e mensagens de falha explícitas em pt-BR
- **F02–F03** Autenticação (throttling, "lembrar-me", URL pretendida) e cadastro, com validação e
  mensagens em pt-BR
- **F04** Shell da aplicação, navegação e componentes de UI compartilhados (Tailwind)
- **F05** Cliente PokeAPI resiliente: timeout, retry com backoff exponencial, rate limit, circuit
  breaker e cache Redis de 24h
- **F06** Sincronização do catálogo via job em fila, idempotente, visível no Horizon
- **F07–F08** Busca ao vivo com debounce, filtro por tipo e listagem paginada
- **F09** Detalhes do Pokémon — **implementado como modal** sobre a grade de resultados (não como
  página roteada; veja a nota abaixo), incluindo cadeia de evolução, lista de golpes, experiência
  base e um painel de fraquezas por tipo — itens que vão além do escopo original do PRD (registrado
  em `docs/prd.md` §7)
- **F10** Favoritos com toggle idempotente, página dedicada com busca/ordenação, e autorização
  cross-user
- **F11** Perfil (nome e troca de senha, com invalidação de outras sessões)
- **F12** Chat em tempo real via Reverb, com presença, histórico paginado, contador de não lidas e
  autorização por canal
- **F13** Suíte Pest (189 casos, 760 assertions, 24 arquivos, ~15s) mais uma segunda camada de
  gates de qualidade (`gates/*.sh`: Pint, Larastan, PHP Insights, Deptrac, PHPCPD, `composer audit`)
  e specs Playwright de regressão visual em `tests/Browser/` — nenhuma dessas duas camadas extras
  fazia parte do escopo original do PRD

**Nota sobre F09:** `/pokemon/{slug}` continua existindo como rota, mas apenas redireciona para
`/?pokemon={slug}`, que reabre o mesmo modal usado pelos cards — não há mais uma página de detalhes
dedicada. `docs/prd.md` foi atualizado para descrever esse comportamento.

**Lacunas conhecidas** (não bloqueiam nenhum critério de aceite do PRD, mas vale registrar):
- Os nomes dos testes Pest estão em inglês, não em frases pt-BR como o PRD F13 pede
- Os critérios de tempo do chat (indicador de presença em até 5s, entrega de mensagem em até 1s)
  não foram medidos de ponta a ponta com dois navegadores nesta validação — a arquitetura (Reverb +
  broadcast síncrono) os suporta, mas não há medição ao vivo registrada

## Fora do escopo do produto

Ver a seção 7 do [PRD](docs/prd.md) para a lista completa (painel administrativo, recuperação de
senha por e-mail, deploy em nuvem, i18n, etc.). Diferente de uma etapa anterior deste projeto, essa
lista agora reflete decisões de escopo do produto, não trabalho ainda não iniciado.

Também está fora do escopo do produto, por decisão de projeto: painel administrativo, recuperação de
senha por e-mail, deploy em nuvem, CI/CD e internacionalização. Detalhes na seção 7 do
[PRD](docs/prd.md).
