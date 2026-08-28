-- Mantém uma única solicitação por usuário em cada torneio.
-- Execute uma vez nos bancos já existentes antes de publicar esta versão.

ALTER TABLE torneio_grupos
    MODIFY COLUMN nome VARCHAR(50) NOT NULL;

DELETE antiga
FROM torneio_solicitacoes antiga
INNER JOIN torneio_solicitacoes recente
        ON recente.torneio_id = antiga.torneio_id
       AND recente.usuario_id = antiga.usuario_id
       AND recente.id > antiga.id;

ALTER TABLE torneio_solicitacoes
    ADD UNIQUE KEY unique_torneio_usuario (torneio_id, usuario_id);

UPDATE torneios
SET status = 'Inscrições Abertas'
WHERE inscricoes_abertas = 1
  AND status = 'Criado';

UPDATE torneios
SET inscricoes_abertas = 0
WHERE status IN ('Em Andamento', 'Finalizado', 'Cancelado');
