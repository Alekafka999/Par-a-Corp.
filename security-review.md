# Revisao de seguranca - Parca Corp

Data: 2026-05-02

## Resumo

O site da Parca Corp esta com uma base de seguranca boa para um site institucional/estatico com formularios simples. A superficie de ataque e pequena: paginas HTML, arquivos `.htaccess`, formularios PHP de captacao, armazenamento em CSV e envio de e-mail.

O basico esta bem encaminhado: diretorios privados bloqueados, listagem de diretorios desligada, PDFs protegidos contra acesso direto, arquivos sensiveis negados e validacao minima dos formularios.

Ainda ha pontos importantes para endurecimento antes de tratar o site como mais robusto ou de alto volume.

## Pontos positivos encontrados

- `_private` bloqueado por `.htaccess`.
- `_private/submissions` bloqueado por `.htaccess`.
- `_private/downloads` bloqueado por `.htaccess`.
- `Options -Indexes` ativo no `.htaccess` raiz e nos diretorios avaliados.
- Bloqueio de `.git`, `_private`, `submissions`, PDFs e arquivos sensiveis no `.htaccess` raiz.
- Headers basicos presentes:
  - `X-Content-Type-Options: nosniff`
  - `Referrer-Policy: strict-origin-when-cross-origin`
  - `X-Frame-Options: SAMEORIGIN`
  - `Permissions-Policy`
- Formularios aceitam apenas `POST`.
- E-mails sao validados com `FILTER_VALIDATE_EMAIL`.
- Quebras de linha sao removidas do e-mail usado em `Reply-To`, mitigando header injection.
- Nao foram encontrados SQL, upload de arquivos ou execucao de comandos nos endpoints revisados.

## Achados e melhorias recomendadas

### 1. Formularios sem protecao anti-spam

Severidade: Media

Arquivos principais:

- `conectaeh/submit-interest.php`
- `meistation/submit-interest.php`
- `rocahub/submit-interest.php`
- `arthunt/submit-interest.php`

Risco:

Bots podem enviar formularios em massa, gerando spam por e-mail, crescimento indevido dos CSVs e logs, alem de possivel abuso do servidor de e-mail.

Melhorias sugeridas:

- Adicionar honeypot invisivel nos formularios.
- Adicionar rate limit simples por IP e janela de tempo.
- Considerar CAPTCHA apenas se o spam persistir.
- Rejeitar submissao muito rapida apos carregamento da pagina, se houver token/timestamp.

### 2. Ausencia de limite de tamanho server-side

Severidade: Media

Risco:

Campos de texto podem receber payloads grandes, causando crescimento exagerado dos CSVs/logs e e-mails muito longos.

Melhorias sugeridas:

- Definir limites no servidor para cada campo.
- Exemplo inicial:
  - nome: 120 caracteres
  - email: 180 caracteres
  - whatsapp: 40 caracteres
  - empresa/cidade/perfil: 160 caracteres
  - comentarios/contexto: 1500 a 3000 caracteres
- Adicionar `maxlength` equivalente no HTML para melhorar UX.

### 3. Risco de CSV injection

Severidade: Media/Baixa

Risco:

Os dados sao gravados com `fputcsv`. Se alguem enviar valores comecando por `=`, `+`, `-` ou `@`, esses campos podem ser interpretados como formulas ao abrir o CSV no Excel ou Google Sheets.

Melhoria sugerida:

- Criar helper para neutralizar valores de CSV antes de salvar.
- Estrategia simples: se o valor comecar com `=`, `+`, `-`, `@`, tab ou carriage return, prefixar com apostrofo.

### 4. PDFs liberados por POST valido, sem token real

Severidade: Baixa

Risco:

Os PDFs estao protegidos contra acesso direto, mas qualquer POST valido com `lead_goal=presentation_download` libera o arquivo. Para materiais de captacao isso pode ser aceitavel. Para material mais sensivel, e pouco restritivo.

Melhorias sugeridas:

- Para materiais sensiveis, gerar token temporario por submissao.
- Alternativa simples: liberar por e-mail em vez de download direto.
- Registrar melhor o evento de download e origem.

### 5. Headers de seguranca ainda podem melhorar

Severidade: Baixa

Risco:

O site ja usa alguns headers importantes, mas ainda nao tem `Content-Security-Policy` nem `Strict-Transport-Security`.

Melhorias sugeridas:

- Adicionar `Strict-Transport-Security` quando HTTPS estiver 100% confirmado em producao:
  - `Strict-Transport-Security: max-age=31536000; includeSubDomains`
- Planejar uma CSP incremental. Como ha CSS/JS inline, comecar em modo `Content-Security-Policy-Report-Only` pode ser mais seguro.
- CSP inicial possivel, a validar:
  - `default-src 'self';`
  - `img-src 'self' data: https:;`
  - `font-src 'self' https://fonts.gstatic.com;`
  - `style-src 'self' 'unsafe-inline' https://fonts.googleapis.com;`
  - `script-src 'self' 'unsafe-inline';`
  - `base-uri 'self';`
  - `frame-ancestors 'self';`
  - `form-action 'self';`

### 6. Logs podem conter dados pessoais

Severidade: Baixa/Media, dependendo do volume

Risco:

Os logs de erro SMTP incluem nome, e-mail, WhatsApp e IP. Isso e util para debug, mas aumenta responsabilidade de privacidade e retencao.

Melhorias sugeridas:

- Reduzir dados pessoais nos logs de erro.
- Criar rotina de limpeza ou rotacao de logs.
- Definir prazo de retencao para CSVs e logs.

## Prioridade sugerida

1. Implementar honeypot e rate limit simples nos formularios.
2. Adicionar limites de tamanho server-side e `maxlength` no HTML.
3. Neutralizar CSV injection antes de `fputcsv`.
4. Rever logs para minimizar dados pessoais e definir retencao.
5. Adicionar HSTS em producao.
6. Planejar CSP incremental.
7. Avaliar token temporario para downloads se os PDFs passarem a ser sensiveis.

## Arquivos revisados

- `.htaccess`
- `_private/.htaccess`
- `_private/submissions/.htaccess`
- `_private/downloads/.htaccess`
- `conectaeh/.htaccess`
- `meistation/.htaccess`
- `rocahub/.htaccess`
- `arthunt/.htaccess`
- `conectaeh/submit-interest.php`
- `meistation/submit-interest.php`
- `rocahub/submit-interest.php`
- `arthunt/submit-interest.php`
- `conectaeh/index.html`
- `meistation/index.html`
- `rocahub/index.html`
- `arthunt/index.html`

## Observacao

Este documento e um ponto de retorno para melhoria posterior. A revisao foi feita por leitura local do codigo e configuracoes do repositorio, sem pentest externo em ambiente de producao.
