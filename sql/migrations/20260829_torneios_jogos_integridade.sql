-- Fortalece regras de torneios e jogos contra duplicidade e corrida.
-- Execute após remover eventuais duplicidades existentes no banco.

ALTER TABLE torneio_times
    ADD UNIQUE KEY unique_torneio_ordem (torneio_id, ordem);

ALTER TABLE torneio_partidas
    ADD COLUMN grupo_key INT GENERATED ALWAYS AS (COALESCE(grupo_id, 0)) STORED,
    ADD COLUMN time_min_id INT GENERATED ALWAYS AS (LEAST(time1_id, time2_id)) STORED,
    ADD COLUMN time_max_id INT GENERATED ALWAYS AS (GREATEST(time1_id, time2_id)) STORED,
    ADD UNIQUE KEY unique_torneio_partida (torneio_id, fase, grupo_key, time_min_id, time_max_id);

ALTER TABLE partidas_2fase_torneio
    ADD COLUMN time_min_id INT GENERATED ALWAYS AS (LEAST(time1_id, time2_id)) STORED,
    ADD COLUMN time_max_id INT GENERATED ALWAYS AS (GREATEST(time1_id, time2_id)) STORED,
    ADD UNIQUE KEY unique_2fase_partida (torneio_id, grupo_id, time_min_id, time_max_id);

ALTER TABLE partidas_2fase_eliminatorias
    ADD UNIQUE KEY unique_2fase_eliminatoria_slot (torneio_id, serie, tipo_eliminatoria, rodada, time1_id, time2_id);

ALTER TABLE torneio_time_integrantes
    ADD UNIQUE KEY unique_participante_em_time (participante_id);
