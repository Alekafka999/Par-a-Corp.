# Conversa Inicial do Blog

Data: 2026-04-10

## Contexto

Objetivo: tirar o blog do papel e decidir a stack inicial.

## Registro da conversa

**Usuário:** Hoje é dia de tirar o blog do papel. Qual a melhor linguagem para fazer isso: PHP?

**Codex:** PHP pode sim ser uma boa escolha, especialmente se a ideia for algo simples, barato de hospedar e rápido de publicar.

**Usuário:** O site em si está hospedado na Hostinger, mas acredito que o blog seja melhor hospedar na Railway

**Codex:** Para esse cenário, a recomendação ficou assim:

- Se a prioridade for simplicidade e velocidade, `Hostinger + WordPress/PHP`
- Se a prioridade for flexibilidade e um projeto mais customizado, `Laravel/PHP na Railway`

Também foi sugerida a separação por subdomínio, como `blog.seudominio.com`.

**Usuário:** Laravel/PHP na Railway

**Codex:** A stack escolhida foi:

- `Laravel/PHP` para a aplicação do blog
- `Railway` para deploy
- `PostgreSQL` como banco de dados
- Possível uso de `blog.seudominio.com` apontado via DNS da Hostinger

Arquitetura sugerida:

- Site institucional continua na `Hostinger`
- Blog roda separado na `Railway`
- Backend e frontend podem começar juntos no próprio Laravel usando `Blade`
- Se houver upload de imagens, vale planejar armazenamento persistente

**Usuário:** Crio uma nova pasta especificamente para o blog?

**Codex:** Sim. A recomendação foi criar uma pasta separada para manter o blog organizado.

Estrutura sugerida:

```text
/raiz-do-projeto
  index.html
  ...
  /blog
```

Vantagens:

- separa o site institucional do blog
- evita misturar arquivos do Laravel com o site atual
- facilita deploy independente
- deixa o projeto mais limpo para manutenção futura

## Decisões tomadas

- O blog será feito com `Laravel/PHP`
- O deploy do blog será na `Railway`
- O blog deve ficar em uma pasta separada, preferencialmente `blog/`
- O site principal continua como está

## Próximos passos sugeridos

1. Criar a pasta `blog/`
2. Inicializar o projeto Laravel dentro dela
3. Configurar banco `PostgreSQL`
4. Preparar deploy na Railway
5. Configurar subdomínio para o blog
