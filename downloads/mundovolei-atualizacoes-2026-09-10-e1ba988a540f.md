# Atualizações realizadas no Mundo Vôlei

Data: 10/09/2026
Servidor: VPS do Mundo Vôlei
Site principal: `mundovolei.online`
Pasta ativa do site: `/var/www/html3`

## 1. Organização dos domínios e pastas

- Confirmado que `mundovolei.online` roda a partir de `/var/www/html3`.
- Ajustado para o domínio apontar direto para `html3`, sem depender de `html3-current`.
- Confirmado alinhamento dos sites:
  - `mundovolei.online` -> `/var/www/html3`
  - `rookland.online` -> `/var/www/html`
  - `withehunter.shop` -> `/var/www/html2`
- `rookland.cloud` foi removido do fluxo do site e configurado para retornar `410 Gone`.
- Backups antigos de pasta foram tratados conforme solicitado.

## 2. Sistema de licenças / chaves

- Verificada a estrutura de chaves em `/var/www/packlibrary-licenses`.
- Identificado o painel/listagem de chaves usadas.
- Confirmada a quantidade de chaves disponíveis a partir da listagem mostrada.

## 3. Jogo de vôlei

### Controles mobile

- Substituídos os botões direcionais no mobile por joystick de arrastar.
- Ajustado para o jogo tentar abrir em tela cheia no mobile.
- Reduzida a velocidade de movimento do jogador.

### Saque e física da bola

- Ajustada a altura/tolerância de contato no saque.
- Separada a força do saque da força do ataque.
- Ajustada a força máxima do saque para permitir bola fora quando forte demais.
- Corrigida a marcação de ponto/fora no saque quando a bola passa do limite da quadra.
- Ajustada a chance/frequência de recepção da IA para evitar spam de defesa.
- Ajustada lógica da IA para deixar bola sair quando o ataque deve cair fora.
- Ajustada lógica de bola fraca passando baixa pela rede para permitir ação do levantador em uma faixa mínima/máxima de altura.
- Ajustado comportamento do receptor para se posicionar melhor quando a bola vem caindo à frente dele.

### Pontuação do jogo

- Criada coluna na tabela de usuários para armazenar pontuação futura do jogo de vôlei.
- Criada lógica para usuário logado ganhar pontos ao vencer partida com 3 sets de 5.
- Após vitória, jogo e sets são resetados conforme regra implementada.

### Debug administrativo do jogo

- Para login como `admin@gmail.com`, foi criado log detalhado do jogo.
- O log registra início/fim do jogo, saque, ataque, altura da bola, altura do player, velocidades, ponto de contato, recepção/manchete da IA, local onde a bola toca o chão e resultado do ponto.
- O log inclui tabela inicial da quadra com dimensões, rede, escala de altura e quadrantes.
- Criado botão no navegador para administrador abrir uma janela com o log completo.
- Criado botão para limpar log.
- Logs ficam bloqueados para acesso público via Nginx.

## 4. Jogos de grupo: participantes e suplentes

Na página de gerenciamento de jogo do grupo:

`/grupos/admin/jogo_grupo.php?id=5&grupo_id=15`

Foram feitos os ajustes:

- Criado campo para definir quantidade máxima de participantes principais.
- A lista principal mostra vagas numeradas mesmo vazias, por exemplo:
  - 1 - João
  - 2 - Caroline
  - 3 - Vaga disponível
- Criada seção separada de suplentes abaixo da lista principal.
- Suplentes fixados em 4 vagas.
- Quando a lista principal enche, novos participantes entram automaticamente como suplentes.
- Corrigida a contagem superior para não somar suplentes junto com participantes principais.
- Mensagens ajustadas para indicar se o usuário entrou na lista principal ou como suplente.

## 5. Segurança geral do site

### Bloqueios no Nginx

Foram bloqueados acessos públicos a arquivos e rotas sensíveis:

- Arquivos de backup como `.php.backup-*`.
- Pasta `/sql/`.
- Pasta `/jogo/debug-logs/`.
- Pasta `/tools/`.
- `check_pontuacao.php`.
- `cleanup_pontuacao_orfa.php`.
- Arquivos ocultos como `.env`, caso apareçam no futuro.
- Arquivos com extensões sensíveis como `.sql`, `.log`, `.bak`, `.old`, `.zip`, `.tar`, `.gz`.

Antes do ajuste, backups e dump SQL estavam retornando `200 OK`. Após o ajuste, passaram a retornar `404`.

### Sessão e cookies

Criado `.user.ini` com endurecimento de sessão:

- `session.cookie_httponly=1`
- `session.cookie_secure=1`
- `session.cookie_samesite=Lax`
- `session.use_strict_mode=1`
- `session.use_only_cookies=1`
- `expose_php=0`

### Banco de dados

- Alterado erro de conexão com banco para não exibir detalhe técnico no navegador.
- Detalhe técnico agora vai apenas para o log do servidor.

### CSRF

- Ativada proteção CSRF em endpoints AJAX que usam `POST`.
- O site já tinha token global no `header.php`; os endpoints agora exigem esse token.
- POST externo sem token retorna `403`.

### Debug antigo

- Removidos vários `error_log` de debug que gravavam SQL, nomes de times, dados de participantes e fluxo interno.
- Mantidos logs úteis de erro real.

## 6. Autenticação e cadastro

- Login/cadastro já usavam hash de senha e prepared statements.
- Confirmado uso de CSRF nos formulários principais.
- Confirmada validação de CPF, email, usuário e senha mínima.
- Corrigido vazamento de erro técnico do banco.
- Sessões/cookies foram endurecidos via `.user.ini`.

Ponto recomendado para uma próxima rodada:

- Criar limite de tentativas de login por IP/email para reduzir brute force.

## 7. Criação e gerenciamento de grupos

Arquivos revisados/corrigidos incluem:

- `/grupos/ajax/criar_grupo.php`
- `/ajax/criar_grupo.php`
- `/grupos/admin/grupo.php`
- `/admin/grupo.php`
- `/grupos/admin/grupos_jogos.php`
- Endpoints de aprovar/rejeitar/listar solicitações de grupo.

Ajustes feitos:

- Adicionado CSRF nas ações da rota `/grupos/admin/grupo.php`.
- Adicionado CSRF nos modais de `/grupos/admin/grupos_jogos.php`.
- Criação de grupo agora limita logo em base64 a 3 MB.
- Criação e edição de logo agora validam se a imagem é real antes de processar.
- Validação de tamanho dos campos:
  - nome até 100 caracteres
  - local até 200 caracteres
  - descrição até 5000 caracteres
  - contato até 1000 caracteres
- Validação de modalidade e nível contra os valores permitidos.
- Bloqueada remoção do administrador do próprio grupo pela lista de membros.
- Permissões dos endpoints de solicitações foram alinhadas:
  - admin do grupo pode listar/aprovar/rejeitar
  - admin geral do site também pode listar/aprovar/rejeitar
- Confirmado que `grupo_membros` possui índice único para evitar duplicidade de membro no mesmo grupo.

## 8. Criação e gerenciamento de jogos

Arquivos revisados/corrigidos incluem:

- `/ajax/criar_jogo.php`
- `/jogos/ajax/criar_jogo.php`
- `/ajax/editar_jogo.php`
- `/jogos/ajax/editar_jogo.php`
- `/ajax/confirmar_presenca.php`
- `/ajax/aprovar_participacao_jogo.php`
- `/jogos/ajax/aprovar_participacao_jogo.php`
- `/ajax/rejeitar_participacao_jogo.php`
- `/jogos/ajax/rejeitar_participacao_jogo.php`
- `/ajax/remover_participacao_jogo.php`
- `/jogos/ajax/remover_participacao_jogo.php`
- `/grupos/ajax/criar_jogo_grupo.php`
- `/grupos/ajax/editar_jogo_grupo.php`
- `/grupos/ajax/excluir_jogo_grupo.php`
- `/grupos/ajax/reabrir_lista_jogo_grupo.php`
- `/grupos/admin/jogo_grupo.php`
- `/includes/functions.php`

Ajustes feitos:

- Validação/normalização de data e hora nos jogos gerais e jogos de grupo.
- Limite de `max_jogadores` no backend entre 1 e 200.
- Normalização da modalidade para o formato esperado pelo banco:
  - `Vôlei` -> `Volei`
  - `Vôlei Quadra` -> `Volei Quadra`
  - `Vôlei Areia` -> `Volei Areia`
  - `Beach Tênis` -> `Beach Tenis`
- Edição de jogo agora recalcula `vagas_disponiveis`.
- Não é permitido reduzir limite de jogadores abaixo do total já confirmado.
- Aprovar/rejeitar/remover participante recalcula vagas.
- Corrigido jogo avulso/público em `confirmar_presenca.php`: antes ele exigia ser membro de grupo mesmo quando o jogo não tinha grupo.
- Confirmação de presença agora usa transação e trava para evitar overbooking.
- Exclusão de jogo de grupo não expõe mais erro técnico no JSON.
- Reabrir lista de jogo de grupo agora bloqueia corretamente se o jogo está em:
  - `Times Criados`
  - `Em Andamento`
  - `Finalizado`
  - `Arquivado`
- Removido loop vazio perigoso em `/grupos/admin/jogo_grupo.php` que poderia travar a página em caso de transação aberta.

## 9. Validações executadas

Foram executadas validações de sintaxe e testes HTTP:

- `php -l` em arquivos alterados.
- Lint geral do escopo de grupos/admin/ajax: `GROUP_SCOPE_LINT_EXIT=0`.
- Lint geral do escopo de jogos/grupos/ajax: `GAMES_SCOPE_LINT_EXIT=0`.
- POST sem CSRF retornando `403`.
- Páginas admin sem login redirecionando para login.
- URLs sensíveis retornando `404` após bloqueio no Nginx.
- `nginx -t` aprovado antes do reload do Nginx.

## 10. Pontos recomendados para próximas melhorias

- Criar rate limit de login por IP/email.
- Revisar duplicação entre `/ajax` e `/jogos/ajax`, porque existem endpoints com comportamento repetido.
- Avaliar mover backups antigos definitivamente para fora de `/var/www`.
- Criar rotina administrativa segura para promover suplente para lista principal.
- Criar testes automatizados mínimos para fluxo de grupo/jogo/presença.
