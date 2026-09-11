CREATE TABLE IF NOT EXISTS `jogo_volei_progresso` (
  `usuario_id` int(11) NOT NULL,
  `nome_time` varchar(30) NOT NULL DEFAULT 'Meu Time',
  `adversario_atual` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `vitorias` int(10) unsigned NOT NULL DEFAULT 0,
  `derrotas` int(10) unsigned NOT NULL DEFAULT 0,
  `campanha_concluida` tinyint(1) NOT NULL DEFAULT 0,
  `partida_token` char(64) DEFAULT NULL,
  `partida_adversario` tinyint(3) unsigned DEFAULT NULL,
  `partida_iniciada_em` timestamp NULL DEFAULT NULL,
  `ultima_partida_em` timestamp NULL DEFAULT NULL,
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`usuario_id`),
  CONSTRAINT `fk_jogo_volei_progresso_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

