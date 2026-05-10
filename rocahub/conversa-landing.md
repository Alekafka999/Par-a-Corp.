# Conversa sobre a landing do Roca Hub

Data: 10 de maio de 2026

## Contexto

O Roca Hub agora tem dominio proprio. A ideia e criar uma nova landing page em uma pasta separada, preservando o site atual e permitindo recomecar com liberdade.

## Conversa

**Usuario:** Brou?

**Codex:** Brou. To aqui. O que vamos mexer hoje?

**Usuario:** Entao... nao sei como fazer. O Roca Hub tem dominio proprio agora.

**Codex:** Boa, isso e um otimo passo. Pra guiar certinho, precisamos decidir se a tarefa e apontar o dominio novo para o Roca Hub ou tambem trocar links e configuracoes dentro do projeto para usar esse dominio.

O caminho geral seria:

1. No painel onde o dominio foi comprado, configurar DNS.
2. No lugar onde o Roca Hub esta hospedado, adicionar o dominio.
3. Ativar HTTPS/SSL.
4. No codigo/config, atualizar URL base, redirects, links de login, e-mails, webhooks ou CORS se existirem.

**Usuario:** Entao... o que a gente pode fazer? Criar uma landing page para o Roca Hub baseada no conteudo que ja tem no site ou fazer algo totalmente novo?

**Codex:** A recomendacao foi um meio termo inteligente: usar o conteudo que ja existe como materia-prima, mas criar uma landing nova de verdade.

Nao copiar o site atual com cara de capa, e tambem nao inventar tudo do zero. O melhor caminho e pegar o que o Roca Hub ja comunica hoje, organizar melhor e transformar em uma primeira pagina mais clara, bonita e comercial.

Opcoes consideradas:

1. **Landing baseada no site atual:** mais rapida, mais segura, mantem a identidade existente. Boa para colocar o dominio no ar logo e transmitir confianca.
2. **Landing totalmente nova:** melhor para reposicionar o Roca Hub, deixar mais profissional, explicar melhor a proposta e preparar para divulgacao, captacao, parceiros ou vendas.

Recomendacao escolhida: **criar uma landing nova baseada no conteudo atual**.

Estrutura sugerida:

1. Hero com o nome **Roca Hub**, uma frase forte explicando o que e, e botao de acao.
2. Secao curta explicando o problema que ele resolve.
3. Secao "Como funciona" com 3 passos simples.
4. Destaques dos recursos e beneficios.
5. Area mais humana: para quem e, por que existe, impacto no campo e no negocio.
6. CTA final: cadastro, contato, entrar na plataforma, WhatsApp ou o que fizer sentido.

**Usuario:** Vou copiar os arquivos do Roca Hub para uma pasta nova. E ai, a gente comeca tudo de novo. Pode ser?

**Codex:** Pode sim. Esse e um bom jeito de trabalhar: preservar o Roca Hub atual e criar a landing nova com liberdade, sem medo de quebrar nada.

Quando os arquivos forem copiados para a pasta nova, o plano e:

1. inspecionar o que ja existe;
2. identificar textos, cores, imagens e estrutura atual;
3. decidir o que reaproveitar;
4. criar a landing nova;
5. testar localmente e passar o link.

Sugestao de pasta: `RocaHub Landing` ou `rocahub-landing` dentro da pasta do GitHub.

**Usuario:** Na `<rocahub>`, adicione um arquivo com o conteudo deste chat.

## Decisao atual

Criar uma nova landing page para o Roca Hub baseada no conteudo existente, mas com estrutura, narrativa e visual renovados.

## Proximo passo

Inspecionar os arquivos atuais em `rocahub`, separar o conteudo reutilizavel e montar a nova landing.
