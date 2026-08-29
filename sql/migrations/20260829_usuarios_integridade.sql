-- Ajustes de integridade para cadastro e gestão de usuários.
-- Execute uma vez em bases antigas antes de publicar os novos formulários.

ALTER TABLE usuarios
  ADD COLUMN IF NOT EXISTS data_aniversario DATE DEFAULT NULL AFTER disponibilidade;

UPDATE usuarios
SET cpf = NULL
WHERE cpf = '';

ALTER TABLE usuarios
  MODIFY genero ENUM('Masculino','Feminino','Outro','Prefiro não informar') NOT NULL DEFAULT 'Masculino';
