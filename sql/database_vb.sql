-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 05/12/2025 às 19:16
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `database_vb`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `atualizacoes_site`
--

CREATE TABLE `atualizacoes_site` (
  `id` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `versao` varchar(50) DEFAULT NULL,
  `descricao` text NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `data_publicacao` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `atualizacoes_site`
--

INSERT INTO `atualizacoes_site` (`id`, `titulo`, `versao`, `descricao`, `ativo`, `data_publicacao`) VALUES
(1, 'Implementação de Funcionalidades de informações', '1.0.1', 'Adição de Registro de Quadras.\r\nAdição de Registro de Profissionais.\r\nAjustes no Sistema de Criação de Torneio.', 1, '2025-12-04 23:34:24');

-- --------------------------------------------------------

--
-- Estrutura para tabela `avaliacoes_reputacao`
--

CREATE TABLE `avaliacoes_reputacao` (
  `id` int(11) NOT NULL,
  `avaliador_id` int(11) NOT NULL,
  `avaliado_id` int(11) NOT NULL,
  `jogo_id` int(11) NOT NULL,
  `tipo` enum('Pontual','Ausente','Bom Jogador','Comportamento') NOT NULL,
  `pontos` int(11) NOT NULL,
  `observacoes` text DEFAULT NULL,
  `data_avaliacao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `avisos`
--

CREATE TABLE `avisos` (
  `id` int(11) NOT NULL,
  `titulo` varchar(100) NOT NULL,
  `conteudo` text NOT NULL,
  `tipo` enum('Geral','Torneio','Jogo','Grupo') NOT NULL,
  `grupo_id` int(11) DEFAULT NULL,
  `jogo_id` int(11) DEFAULT NULL,
  `torneio_id` int(11) DEFAULT NULL,
  `criado_por` int(11) NOT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `ativo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `confirmacoes_presenca`
--

CREATE TABLE `confirmacoes_presenca` (
  `id` int(11) NOT NULL,
  `jogo_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `status` enum('Confirmado','Ausente','Pendente') DEFAULT 'Pendente',
  `data_confirmacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `observacoes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `confirmacoes_presenca`
--

INSERT INTO `confirmacoes_presenca` (`id`, `jogo_id`, `usuario_id`, `status`, `data_confirmacao`, `observacoes`) VALUES
(34, 25, 21, 'Confirmado', '2025-11-25 19:40:20', NULL),
(35, 26, 37, 'Confirmado', '2025-12-05 02:13:23', NULL),
(36, 26, 21, 'Confirmado', '2025-12-05 02:59:06', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `dicas`
--

CREATE TABLE `dicas` (
  `id` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `conteudo` text NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `data_criacao` datetime NOT NULL DEFAULT current_timestamp(),
  `data_atualizacao` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `galeria_fotos`
--

CREATE TABLE `galeria_fotos` (
  `id` int(11) NOT NULL,
  `titulo` varchar(255) DEFAULT NULL,
  `descricao` text DEFAULT NULL,
  `caminho` varchar(500) NOT NULL COMMENT 'Caminho da imagem',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `data_upload` datetime NOT NULL DEFAULT current_timestamp(),
  `data_atualizacao` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `grupos`
--

CREATE TABLE `grupos` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `logo_id` int(11) DEFAULT NULL,
  `descricao` text DEFAULT NULL,
  `nivel` enum('Iniciante','Amador','Avançado','Profissional') DEFAULT NULL,
  `administrador_id` int(11) NOT NULL,
  `local_principal` varchar(200) DEFAULT NULL,
  `contato` text DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `modalidade` enum('Vôlei','Vôlei Quadra','Vôlei Areia','Beach Tênis') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `grupos`
--

INSERT INTO `grupos` (`id`, `nome`, `logo_id`, `descricao`, `nivel`, `administrador_id`, `local_principal`, `contato`, `ativo`, `data_criacao`, `modalidade`) VALUES
(11, 'Diretoria do Volley', 6, 'Aqui a resenha é garantida, mas a bola... bom, a bola a gente tenta acertar. O que importa é a alegria, a cerveja gelada depois e o migué na hora da defesa. Membros: os maiores (e mais divertidos) estrategistas do vôlei de todos os tempos.', 'Amador', 26, '8000 Sports', '55991773439', 1, '2025-10-30 16:30:21', 'Vôlei'),
(14, 'Power Beachs', 11, 'Um grupo para a gente combinar os jogos, marcar a presença e, claro, falar sobre as jogadas épicas (e os micos também!). O mais importante é a diversão e a amizade.', 'Amador', 24, '8000 Sports', '559909083439', 1, '2025-10-30 16:49:02', 'Beach Tênis'),
(15, 'Unidos pelo Volei', 12, 'Beber antes depois e durante uma partida.\r\nSó passe de toque.\r\nFicar Conversando enquanto os colegas esperam.', 'Iniciante', 21, '8000 Sports', '55991773439', 1, '2025-10-30 21:11:54', 'Vôlei Areia'),
(16, 'teste', 13, 'asdasdasdasd', 'Amador', 1, '8000 Sports', '55991773439', 1, '2025-11-14 18:19:27', 'Vôlei');

-- --------------------------------------------------------

--
-- Estrutura para tabela `grupo_membros`
--

CREATE TABLE `grupo_membros` (
  `id` int(11) NOT NULL,
  `grupo_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `data_entrada` timestamp NOT NULL DEFAULT current_timestamp(),
  `ativo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `grupo_membros`
--

INSERT INTO `grupo_membros` (`id`, `grupo_id`, `usuario_id`, `data_entrada`, `ativo`) VALUES
(13, 11, 26, '2025-10-30 16:30:21', 1),
(17, 14, 24, '2025-10-30 16:49:02', 1),
(18, 14, 1, '2025-10-30 18:01:32', 0),
(19, 15, 21, '2025-10-30 21:11:54', 1),
(20, 15, 24, '2025-10-30 21:18:24', 1),
(21, 15, 25, '2025-10-30 21:19:17', 1),
(22, 15, 27, '2025-10-30 21:19:39', 1),
(23, 16, 1, '2025-11-14 18:19:27', 1),
(24, 15, 23, '2025-11-14 18:38:14', 1),
(25, 15, 28, '2025-11-14 18:41:23', 1),
(26, 15, 2, '2025-11-14 18:42:00', 1),
(27, 15, 22, '2025-11-14 18:48:00', 1),
(28, 15, 29, '2025-11-14 18:49:52', 1),
(29, 15, 30, '2025-11-14 18:50:38', 1),
(30, 15, 31, '2025-11-14 18:56:29', 1),
(31, 15, 34, '2025-11-14 18:58:51', 1),
(32, 15, 33, '2025-11-14 18:59:08', 1),
(33, 15, 35, '2025-11-14 19:55:48', 1),
(34, 15, 36, '2025-11-14 19:56:39', 1),
(35, 15, 37, '2025-11-14 19:58:33', 1),
(36, 15, 39, '2025-11-14 20:00:11', 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `jogos`
--

CREATE TABLE `jogos` (
  `id` int(11) NOT NULL,
  `grupo_id` int(11) DEFAULT NULL,
  `titulo` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL,
  `data_jogo` datetime NOT NULL,
  `data_fim` datetime DEFAULT NULL,
  `local` varchar(200) NOT NULL,
  `max_jogadores` int(11) DEFAULT 12,
  `vagas_disponiveis` int(11) DEFAULT 12,
  `status` enum('Aberto','Fechado','Em Andamento','Finalizado') DEFAULT 'Aberto',
  `criado_por` int(11) NOT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `modalidade` enum('Volei','Volei Quadra','Volei Areia','Beach Tenis') DEFAULT NULL,
  `contato` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `jogos`
--

INSERT INTO `jogos` (`id`, `grupo_id`, `titulo`, `descricao`, `data_jogo`, `data_fim`, `local`, `max_jogadores`, `vagas_disponiveis`, `status`, `criado_por`, `data_criacao`, `modalidade`, `contato`) VALUES
(25, 15, 'jogoo bora', 'xffxgdf', '2025-11-25 16:40:00', '2025-11-26 16:40:00', '8000', 6, 6, 'Finalizado', 21, '2025-11-25 19:40:20', 'Volei', '55991773439'),
(26, NULL, 'só vem', 'No 8000 só vem quarteto', '2025-12-30 23:12:00', '2025-12-04 00:14:00', '8000', 6, 6, 'Aberto', 37, '2025-12-05 02:13:23', 'Volei', '3984792873423');

-- --------------------------------------------------------

--
-- Estrutura para tabela `logos_grupos`
--

CREATE TABLE `logos_grupos` (
  `id` int(11) NOT NULL,
  `caminho` varchar(255) NOT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `logos_grupos`
--

INSERT INTO `logos_grupos` (`id`, `caminho`, `data_criacao`) VALUES
(1, 'assets/arquivos/logosgrupos/1.png', '2025-10-30 14:55:02'),
(2, '', '2025-10-30 14:59:52'),
(3, 'assets/arquivos/logosgrupos/3.png', '2025-10-30 16:18:28'),
(4, 'assets/arquivos/logosgrupos/4.png', '2025-10-30 16:25:28'),
(5, 'assets/arquivos/logosgrupos/5.png', '2025-10-30 16:26:30'),
(6, 'assets/arquivos/logosgrupos/6.png', '2025-10-30 16:30:21'),
(11, 'assets/arquivos/logosgrupos/11.png', '2025-10-30 16:52:22'),
(12, 'assets/arquivos/logosgrupos/12.png', '2025-10-30 21:11:54'),
(13, 'assets/arquivos/logosgrupos/13.png', '2025-11-14 18:19:27');

-- --------------------------------------------------------

--
-- Estrutura para tabela `loja_produtos`
--

CREATE TABLE `loja_produtos` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `descricao` text DEFAULT NULL,
  `valor` decimal(10,2) NOT NULL,
  `imagem` varchar(255) DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_atualizacao` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `loja_produtos`
--

INSERT INTO `loja_produtos` (`id`, `nome`, `descricao`, `valor`, `imagem`, `ativo`, `data_criacao`, `data_atualizacao`) VALUES
(1, 'Bola de Volei', 'Bola de volei de muito boa qualidade.', 199.00, 'assets/arquivos/produtos/6917b9be5dbb7_1763162558.jpg', 1, '2025-11-14 23:22:38', NULL),
(2, 'Joelheira', 'Protege os joelhos contra impactos.', 55.00, 'assets/arquivos/produtos/6917b9e87414f_1763162600.jpg', 1, '2025-11-14 23:23:20', NULL),
(3, 'Manguito', 'Protege o antebraço', 300.00, 'assets/arquivos/produtos/69324876bb0dc_1764903030.png', 1, '2025-12-05 02:50:30', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `notificacoes`
--

CREATE TABLE `notificacoes` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `titulo` varchar(100) NOT NULL,
  `mensagem` text NOT NULL,
  `lida` tinyint(1) DEFAULT 0,
  `criada_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `notificacoes`
--

INSERT INTO `notificacoes` (`id`, `usuario_id`, `titulo`, `mensagem`, `lida`, `criada_em`) VALUES
(1, 2, 'Penalização em jogo', 'Você foi penalizado pelo criador do jogo jogoo bora por atitude negativa.', 1, '2025-10-30 19:00:01'),
(2, 2, 'Penalização em jogo', 'Você foi penalizado em jogoo bora (-15 pts) por gravidade falta do jogo.', 1, '2025-10-30 20:22:29'),
(3, 24, 'Solicitação de participação no jogo', 'Você recebeu uma solicitação de entrada no seu jogo #20.', 1, '2025-10-30 20:24:57'),
(4, 2, 'Você foi aceito no jogo', 'Sua solicitação para o jogo #20 foi aprovada pelo criador.', 1, '2025-10-30 20:25:20'),
(5, 24, 'Solicitação de participação no jogo', 'Você recebeu uma solicitação de entrada no seu jogo #21.', 1, '2025-10-30 20:26:02'),
(6, 2, 'Você foi aceito no jogo', 'Sua solicitação para o jogo #21 foi aprovada pelo criador.', 1, '2025-10-30 20:26:17'),
(7, 2, 'Penalização em jogo', 'Você foi penalizado em jogoo bora (-15 pts) por gravidade falta do jogo.', 1, '2025-10-30 20:30:02'),
(8, 24, 'Solicitação de participação no jogo', 'Você recebeu uma solicitação de entrada no seu jogo #22.', 1, '2025-10-30 20:33:05'),
(9, 2, 'Você foi aceito no jogo', 'Sua solicitação para o jogo #22 foi aprovada pelo criador.', 1, '2025-10-30 20:33:32'),
(10, 2, 'Penalização em jogo', 'Você foi penalizado em jogoo bora (-15 pts) por gravidade falta do jogo.', 1, '2025-10-30 20:36:31'),
(11, 24, 'Solicitação de participação no jogo', 'Você recebeu uma solicitação de entrada no seu jogo #23.', 1, '2025-10-30 20:38:44'),
(12, 2, 'Você foi aceito no jogo', 'Sua solicitação para o jogo #23 foi aprovada pelo criador.', 0, '2025-10-30 20:39:21'),
(13, 2, 'Avaliação positiva', 'Você foi avaliado em jogoo bora (+4 pts) por jogador prestativo.', 0, '2025-10-30 20:44:51'),
(14, 21, 'Nova solicitação no grupo', 'Você recebeu uma solicitação de entrada no grupo #15.', 1, '2025-10-30 21:18:24'),
(15, 21, 'Nova solicitação no grupo', 'Você recebeu uma solicitação de entrada no grupo #15.', 1, '2025-10-30 21:19:17'),
(16, 21, 'Nova solicitação no grupo', 'Você recebeu uma solicitação de entrada no grupo #15.', 1, '2025-10-30 21:19:39'),
(17, 27, 'Solicitação de grupo aprovada', 'Sua entrada no grupo #15 foi aprovada.', 1, '2025-10-30 21:20:00'),
(18, 24, 'Solicitação de grupo aprovada', 'Sua entrada no grupo #15 foi aprovada.', 0, '2025-10-30 21:20:01'),
(19, 25, 'Solicitação de grupo aprovada', 'Sua entrada no grupo #15 foi aprovada.', 0, '2025-10-30 21:20:02'),
(20, 21, 'Solicitação de participação no jogo', 'Você recebeu uma solicitação de entrada no seu jogo #24.', 1, '2025-10-30 22:39:57'),
(21, 27, 'Você foi aceito no jogo', 'Sua solicitação para o jogo #24 foi aprovada pelo criador.', 1, '2025-10-30 22:40:29'),
(22, 27, 'Avaliação positiva', 'Você foi avaliado em jogo da pamela (+4 pts) por jogador prestativo.', 1, '2025-10-30 22:45:57'),
(23, 21, 'Nova solicitação no grupo', 'Você recebeu uma solicitação de entrada no grupo #15.', 1, '2025-11-14 18:38:14'),
(24, 23, 'Solicitação de grupo aprovada', 'Sua entrada no grupo #15 foi aprovada.', 0, '2025-11-14 18:38:36'),
(25, 21, 'Nova solicitação no grupo', 'Você recebeu uma solicitação de entrada no grupo #15.', 1, '2025-11-14 18:41:23'),
(26, 21, 'Nova solicitação no grupo', 'Você recebeu uma solicitação de entrada no grupo #15.', 1, '2025-11-14 18:42:00'),
(27, 28, 'Solicitação de grupo aprovada', 'Sua entrada no grupo #15 foi aprovada.', 0, '2025-11-14 18:45:37'),
(28, 2, 'Solicitação de grupo aprovada', 'Sua entrada no grupo #15 foi aprovada.', 0, '2025-11-14 18:45:39'),
(29, 21, 'Nova solicitação no grupo', 'Você recebeu uma solicitação de entrada no grupo #15.', 1, '2025-11-14 18:48:00'),
(30, 21, 'Nova solicitação no grupo', 'Você recebeu uma solicitação de entrada no grupo #15.', 1, '2025-11-14 18:49:52'),
(31, 21, 'Nova solicitação no grupo', 'Você recebeu uma solicitação de entrada no grupo #15.', 1, '2025-11-14 18:50:38'),
(32, 30, 'Solicitação de grupo aprovada', 'Sua entrada no grupo #15 foi aprovada.', 0, '2025-11-14 18:51:16'),
(33, 29, 'Solicitação de grupo aprovada', 'Sua entrada no grupo #15 foi aprovada.', 0, '2025-11-14 18:51:16'),
(34, 22, 'Solicitação de grupo aprovada', 'Sua entrada no grupo #15 foi aprovada.', 0, '2025-11-14 18:51:18'),
(35, 21, 'Nova solicitação no grupo', 'Você recebeu uma solicitação de entrada no grupo #15.', 1, '2025-11-14 18:56:29'),
(36, 21, 'Nova solicitação no grupo', 'Você recebeu uma solicitação de entrada no grupo #15.', 1, '2025-11-14 18:58:51'),
(37, 21, 'Nova solicitação no grupo', 'Você recebeu uma solicitação de entrada no grupo #15.', 1, '2025-11-14 18:59:08'),
(38, 34, 'Solicitação de grupo aprovada', 'Sua entrada no grupo #15 foi aprovada.', 0, '2025-11-14 18:59:20'),
(39, 33, 'Solicitação de grupo aprovada', 'Sua entrada no grupo #15 foi aprovada.', 0, '2025-11-14 18:59:21'),
(40, 31, 'Solicitação de grupo aprovada', 'Sua entrada no grupo #15 foi aprovada.', 0, '2025-11-14 18:59:21'),
(41, 21, 'Nova solicitação no grupo', 'Você recebeu uma solicitação de entrada no grupo #15.', 1, '2025-11-14 19:55:48'),
(42, 21, 'Nova solicitação no grupo', 'Você recebeu uma solicitação de entrada no grupo #15.', 1, '2025-11-14 19:56:39'),
(43, 21, 'Nova solicitação no grupo', 'Você recebeu uma solicitação de entrada no grupo #15.', 1, '2025-11-14 19:58:33'),
(44, 21, 'Nova solicitação no grupo', 'Você recebeu uma solicitação de entrada no grupo #15.', 1, '2025-11-14 20:00:11'),
(45, 39, 'Solicitação de grupo aprovada', 'Sua entrada no grupo #15 foi aprovada.', 0, '2025-11-14 20:00:30'),
(46, 37, 'Solicitação de grupo aprovada', 'Sua entrada no grupo #15 foi aprovada.', 1, '2025-11-14 20:00:32'),
(47, 36, 'Solicitação de grupo aprovada', 'Sua entrada no grupo #15 foi aprovada.', 0, '2025-11-14 20:00:33'),
(48, 35, 'Solicitação de grupo aprovada', 'Sua entrada no grupo #15 foi aprovada.', 0, '2025-11-14 20:00:34'),
(49, 37, 'Solicitação de participação no jogo', 'Você recebeu uma solicitação de entrada no seu jogo #26.', 1, '2025-12-05 02:59:06'),
(50, 21, 'Você foi aceito no jogo', 'Sua solicitação para o jogo #26 foi aprovada pelo criador.', 0, '2025-12-05 02:59:25'),
(51, 37, 'Solicitação de participação aprovada', 'Sua solicitação de participação no torneio \"Verao 2026\" foi aprovada!', 1, '2025-12-05 17:08:32'),
(52, 37, 'Solicitação de participação aprovada', 'Sua solicitação de participação no torneio \"Verao 2026\" foi aprovada!', 1, '2025-12-05 17:11:07'),
(53, 37, 'Solicitação de participação aprovada', 'Sua solicitação de participação no torneio \"Verao 2026\" foi aprovada!', 1, '2025-12-05 17:16:20'),
(54, 37, 'Você foi removido do torneio', 'Você foi removido do torneio \"Verao 2026\" pelo administrador.', 1, '2025-12-05 17:16:27'),
(55, 37, 'Solicitação de participação aprovada', 'Sua solicitação de participação no torneio \"Verao 2026\" foi aprovada!', 0, '2025-12-05 17:17:34');

-- --------------------------------------------------------

--
-- Estrutura para tabela `pagamentos`
--

CREATE TABLE `pagamentos` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `tipo_pagamento` enum('mensalidade','torneio','evento','outro') NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `descricao` text DEFAULT NULL,
  `metodo_pagamento` enum('pix','cartao','debito','boleto') NOT NULL,
  `status` enum('Pendente','Aprovado','Cancelado','Rejeitado') DEFAULT 'Pendente',
  `pagamento_id` varchar(50) DEFAULT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_atualizacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `partidas`
--

CREATE TABLE `partidas` (
  `id` int(11) NOT NULL,
  `jogo_id` int(11) NOT NULL,
  `time1_id` int(11) NOT NULL,
  `time2_id` int(11) NOT NULL,
  `pontos_time1` int(11) DEFAULT 0,
  `pontos_time2` int(11) DEFAULT 0,
  `sets_time1` int(11) DEFAULT 0,
  `sets_time2` int(11) DEFAULT 0,
  `status` enum('Agendada','Em Andamento','Finalizada') DEFAULT 'Agendada',
  `data_inicio` datetime DEFAULT NULL,
  `data_fim` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `profissionais`
--

CREATE TABLE `profissionais` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `modalidade` varchar(255) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `descricao` text DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `profissionais`
--

INSERT INTO `profissionais` (`id`, `nome`, `modalidade`, `telefone`, `email`, `descricao`, `ativo`) VALUES
(1, 'Eduardo Gaier', 'Treinado Fisico Volei', '55991773439', 'eduardogaier@gmail.com', 'Vagas alunos de seg a sexta feira das 08 a 11 no 8000 sports', 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `quadras`
--

CREATE TABLE `quadras` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `endereco` text NOT NULL,
  `valor_hora` decimal(10,2) NOT NULL DEFAULT 0.00,
  `descricao` text DEFAULT NULL,
  `foto` varchar(500) DEFAULT NULL COMMENT 'Caminho da foto',
  `tipo` enum('areia','quadra') NOT NULL DEFAULT 'quadra',
  `localizacao` text DEFAULT NULL COMMENT 'Link do Google Maps ou coordenadas',
  `ativo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `quadras`
--

INSERT INTO `quadras` (`id`, `nome`, `endereco`, `valor_hora`, `descricao`, `foto`, `tipo`, `localizacao`, `ativo`) VALUES
(1, '8000 Sports', 'Av Prefeito Evandro Berh 1600', 160.00, 'Em frente a Angelos Faixa Velha', 'assets/arquivos/quadras/693243d081dde_1764901840_imagem_2025-12-04_233012459.png', 'areia', 'https://share.google/kRxwkMPMGIND77rnB', 1),
(2, 'DGT Sports', 'R. Pedro Santini, 1633 - Nossa Sra. de Lourdes, Santa Maria - RS, 97095-000', 160.00, 'Local com churrasqueira e diversas quadras ao ar livre.', 'assets/arquivos/quadras/69324440e3726_1764901952_imagem_2025-12-04_233217673.png', 'areia', 'https://share.google/kLbcWYrXUsGQtqzDe', 1),
(3, 'PELEIA FC', 'R. Duque de Caxias, 2653', 150.00, 'Na principal duque de caxias', 'assets/arquivos/quadras/693247e73fec0_1764902887_imagem_2025-12-04_234804706.png', 'quadra', 'https://share.google/Fq4gHtXrabITfn8Cp', 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `sistemas_pontuacao`
--

CREATE TABLE `sistemas_pontuacao` (
  `id` int(11) NOT NULL,
  `grupo_id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `data_inicial` date NOT NULL,
  `data_final` date NOT NULL,
  `quantidade_jogos` int(11) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) DEFAULT 1,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `sistemas_pontuacao`
--

INSERT INTO `sistemas_pontuacao` (`id`, `grupo_id`, `nome`, `data_inicial`, `data_final`, `quantidade_jogos`, `ativo`, `data_criacao`) VALUES
(2, 16, 'Teste', '2025-11-14', '2025-11-30', 6, 1, '2025-11-14 18:30:45'),
(3, 15, 'Vale xis', '2025-11-14', '2025-12-19', 6, 1, '2025-11-14 19:01:01');

-- --------------------------------------------------------

--
-- Estrutura para tabela `sistema_pontuacao_jogos`
--

CREATE TABLE `sistema_pontuacao_jogos` (
  `id` int(11) NOT NULL,
  `sistema_id` int(11) NOT NULL,
  `numero_jogo` int(11) NOT NULL,
  `data_jogo` date NOT NULL,
  `descricao` text DEFAULT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `sistema_pontuacao_jogos`
--

INSERT INTO `sistema_pontuacao_jogos` (`id`, `sistema_id`, `numero_jogo`, `data_jogo`, `descricao`, `data_criacao`) VALUES
(2, 2, 1, '2025-11-14', 'sdasd', '2025-11-14 18:31:44'),
(4, 3, 1, '2025-11-14', '1', '2025-11-14 20:00:57'),
(5, 3, 2, '2025-11-21', '2', '2025-11-22 11:16:59');

-- --------------------------------------------------------

--
-- Estrutura para tabela `sistema_pontuacao_participantes`
--

CREATE TABLE `sistema_pontuacao_participantes` (
  `id` int(11) NOT NULL,
  `jogo_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `data_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `sistema_pontuacao_participantes`
--

INSERT INTO `sistema_pontuacao_participantes` (`id`, `jogo_id`, `usuario_id`, `data_registro`) VALUES
(14, 4, 36, '2025-11-14 20:00:57'),
(15, 4, 29, '2025-11-14 20:00:57'),
(16, 4, 35, '2025-11-14 20:00:57'),
(17, 4, 22, '2025-11-14 20:00:57'),
(18, 4, 21, '2025-11-14 20:00:57'),
(19, 4, 28, '2025-11-14 20:00:57'),
(21, 4, 34, '2025-11-14 20:00:57'),
(22, 4, 2, '2025-11-14 20:00:57'),
(24, 4, 23, '2025-11-14 20:00:57'),
(25, 4, 25, '2025-11-14 20:00:57'),
(27, 4, 24, '2025-11-14 20:00:57'),
(28, 4, 33, '2025-11-14 20:00:57'),
(29, 4, 37, '2025-11-14 20:00:57'),
(30, 4, 30, '2025-11-14 20:00:57'),
(31, 5, 22, '2025-11-22 11:16:59'),
(32, 5, 21, '2025-11-22 11:16:59'),
(33, 5, 28, '2025-11-22 11:16:59'),
(34, 5, 34, '2025-11-22 11:16:59'),
(35, 5, 2, '2025-11-22 11:16:59'),
(36, 5, 31, '2025-11-22 11:16:59'),
(37, 5, 23, '2025-11-22 11:16:59'),
(38, 5, 25, '2025-11-22 11:16:59'),
(39, 5, 39, '2025-11-22 11:16:59'),
(40, 5, 24, '2025-11-22 11:16:59'),
(41, 5, 33, '2025-11-22 11:16:59'),
(42, 5, 37, '2025-11-22 11:16:59');

-- --------------------------------------------------------

--
-- Estrutura para tabela `sistema_pontuacao_pontos`
--

CREATE TABLE `sistema_pontuacao_pontos` (
  `id` int(11) NOT NULL,
  `jogo_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `pontos` decimal(10,2) NOT NULL DEFAULT 0.00,
  `data_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `sistema_pontuacao_pontos`
--

INSERT INTO `sistema_pontuacao_pontos` (`id`, `jogo_id`, `usuario_id`, `pontos`, `data_registro`) VALUES
(1, 2, 1, 1.00, '2025-11-14 18:31:52'),
(45, 4, 2, 18.00, '2025-11-15 10:27:46'),
(46, 4, 21, 15.00, '2025-11-15 10:27:46'),
(47, 4, 22, 6.00, '2025-11-15 10:27:46'),
(48, 4, 23, 5.00, '2025-11-15 10:27:46'),
(49, 4, 24, 7.00, '2025-11-15 10:27:46'),
(50, 4, 25, 6.00, '2025-11-15 10:27:46'),
(52, 4, 28, 25.00, '2025-11-15 10:27:46'),
(53, 4, 29, 5.00, '2025-11-15 10:27:46'),
(54, 4, 30, 19.00, '2025-11-15 10:27:46'),
(56, 4, 33, 2.00, '2025-11-15 10:27:46'),
(57, 4, 34, 6.00, '2025-11-15 10:27:46'),
(58, 4, 35, 0.00, '2025-11-15 10:27:46'),
(59, 4, 36, 12.00, '2025-11-15 10:27:46'),
(60, 4, 37, 7.00, '2025-11-15 10:27:46'),
(62, 5, 2, 19.00, '2025-11-22 11:18:11'),
(63, 5, 21, 7.00, '2025-11-22 11:18:11'),
(64, 5, 22, 3.00, '2025-11-22 11:18:11'),
(65, 5, 23, 4.00, '2025-11-22 11:18:11'),
(66, 5, 24, 12.00, '2025-11-22 11:18:11'),
(67, 5, 25, 4.00, '2025-11-22 11:18:11'),
(68, 5, 28, 22.00, '2025-11-22 11:18:11'),
(69, 5, 31, 25.00, '2025-11-22 11:18:11'),
(70, 5, 33, 4.00, '2025-11-22 11:18:11'),
(71, 5, 34, 9.00, '2025-11-22 11:18:11'),
(72, 5, 37, 19.00, '2025-11-22 11:18:11'),
(73, 5, 39, 8.00, '2025-11-22 11:18:11');

-- --------------------------------------------------------

--
-- Estrutura para tabela `sistema_pontuacao_times`
--

CREATE TABLE `sistema_pontuacao_times` (
  `id` int(11) NOT NULL,
  `jogo_id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL,
  `cor` varchar(20) DEFAULT '#007bff',
  `ordem` int(11) DEFAULT 0,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `sistema_pontuacao_time_jogadores`
--

CREATE TABLE `sistema_pontuacao_time_jogadores` (
  `id` int(11) NOT NULL,
  `time_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `times`
--

CREATE TABLE `times` (
  `id` int(11) NOT NULL,
  `jogo_id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL,
  `cor` varchar(20) DEFAULT '#007bff'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `time_jogadores`
--

CREATE TABLE `time_jogadores` (
  `id` int(11) NOT NULL,
  `time_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `posicao` enum('Levantador','Oposto','Central','Ponteiro','Líbero') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `torneios`
--

CREATE TABLE `torneios` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL,
  `grupo_id` int(11) DEFAULT NULL,
  `tipo` enum('grupo','avulso') DEFAULT 'grupo',
  `data_inicio` datetime NOT NULL,
  `data_fim` datetime DEFAULT NULL,
  `max_participantes` int(11) DEFAULT 16,
  `quantidade_times` int(11) DEFAULT NULL,
  `integrantes_por_time` int(11) DEFAULT NULL,
  `modalidade` enum('todos_contra_todos','todos_chaves') DEFAULT NULL,
  `quantidade_grupos` int(11) DEFAULT NULL,
  `status` enum('Criado','Inscrições Abertas','Em Andamento','Finalizado','Cancelado') DEFAULT 'Criado',
  `criado_por` int(11) NOT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `inscricoes_abertas` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `torneios`
--

INSERT INTO `torneios` (`id`, `nome`, `descricao`, `grupo_id`, `tipo`, `data_inicio`, `data_fim`, `max_participantes`, `quantidade_times`, `integrantes_por_time`, `modalidade`, `quantidade_grupos`, `status`, `criado_por`, `data_criacao`, `inscricoes_abertas`) VALUES
(21, 'Verao 2026', NULL, NULL, 'avulso', '2025-12-31 00:00:00', NULL, 32, 8, 4, 'todos_chaves', 2, 'Criado', 21, '2025-12-05 17:02:20', 1),
(22, 'Unidos pelo Volei', NULL, 15, 'grupo', '2025-12-31 00:00:00', NULL, 16, 4, 4, 'todos_contra_todos', 2, 'Criado', 21, '2025-12-05 18:05:31', 0);

-- --------------------------------------------------------

--
-- Estrutura para tabela `torneio_chaves`
--

CREATE TABLE `torneio_chaves` (
  `id` int(11) NOT NULL,
  `torneio_id` int(11) NOT NULL,
  `fase` enum('Oitavas','Quartas','Semi','Final','3º Lugar') NOT NULL,
  `partida_numero` int(11) NOT NULL,
  `jogador1_id` int(11) DEFAULT NULL,
  `jogador2_id` int(11) DEFAULT NULL,
  `vencedor_id` int(11) DEFAULT NULL,
  `pontos_jogador1` int(11) DEFAULT 0,
  `pontos_jogador2` int(11) DEFAULT 0,
  `data_partida` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `torneio_chaves_times`
--

CREATE TABLE `torneio_chaves_times` (
  `id` int(11) NOT NULL,
  `torneio_id` int(11) NOT NULL,
  `fase` enum('Quartas','Semi','Final','3º Lugar') NOT NULL,
  `chave_numero` int(11) NOT NULL COMMENT 'Número da chave (1, 2, 3, 4 para quartas)',
  `time1_id` int(11) DEFAULT NULL,
  `time2_id` int(11) DEFAULT NULL,
  `vencedor_id` int(11) DEFAULT NULL,
  `pontos_time1` int(11) DEFAULT 0,
  `pontos_time2` int(11) DEFAULT 0,
  `data_partida` datetime DEFAULT NULL,
  `status` enum('Agendada','Em Andamento','Finalizada') DEFAULT 'Agendada',
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `torneio_chaves_times`
--

INSERT INTO `torneio_chaves_times` (`id`, `torneio_id`, `fase`, `chave_numero`, `time1_id`, `time2_id`, `vencedor_id`, `pontos_time1`, `pontos_time2`, `data_partida`, `status`, `data_criacao`) VALUES
(1, 21, 'Semi', 1, 261, 265, 261, 15, 14, NULL, 'Finalizada', '2025-12-05 17:57:20'),
(2, 21, 'Semi', 2, 264, 260, 260, 12, 15, NULL, 'Finalizada', '2025-12-05 17:57:20'),
(3, 21, 'Final', 1, 261, 260, 260, 14, 15, NULL, 'Finalizada', '2025-12-05 17:57:20'),
(4, 21, '3º Lugar', 1, 265, 264, 264, 13, 15, NULL, 'Finalizada', '2025-12-05 17:57:20');

-- --------------------------------------------------------

--
-- Estrutura para tabela `torneio_classificacao`
--

CREATE TABLE `torneio_classificacao` (
  `id` int(11) NOT NULL,
  `torneio_id` int(11) NOT NULL,
  `time_id` int(11) NOT NULL,
  `vitorias` int(11) DEFAULT 0,
  `derrotas` int(11) DEFAULT 0,
  `empates` int(11) DEFAULT 0,
  `pontos_pro` int(11) DEFAULT 0 COMMENT 'Pontos marcados',
  `pontos_contra` int(11) DEFAULT 0 COMMENT 'Pontos sofridos',
  `saldo_pontos` int(11) DEFAULT 0 COMMENT 'Pontos a favor - pontos contra',
  `average` decimal(10,2) DEFAULT 0.00 COMMENT 'Average = pontos_pro / pontos_contra (quando pontos_contra > 0)',
  `pontos_total` int(11) DEFAULT 0 COMMENT 'Pontos totais (3 vitórias, 1 empate, 0 derrota)',
  `posicao` int(11) DEFAULT NULL COMMENT 'Posição na classificação',
  `data_atualizacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `torneio_classificacao`
--

INSERT INTO `torneio_classificacao` (`id`, `torneio_id`, `time_id`, `vitorias`, `derrotas`, `empates`, `pontos_pro`, `pontos_contra`, `saldo_pontos`, `average`, `pontos_total`, `posicao`, `data_atualizacao`) VALUES
(97, 21, 259, 1, 2, 0, 35, 43, -8, 0.81, 3, 5, '2025-12-05 17:56:41'),
(98, 21, 260, 4, 1, 0, 72, 62, 10, 1.16, 12, 3, '2025-12-05 18:01:11'),
(99, 21, 261, 4, 1, 0, 74, 61, 13, 1.21, 12, 2, '2025-12-05 18:01:11'),
(100, 21, 262, 0, 3, 0, 34, 45, -11, 0.76, 0, 7, '2025-12-05 17:57:07'),
(101, 21, 263, 0, 3, 0, 26, 45, -19, 0.58, 0, 8, '2025-12-05 17:57:07'),
(102, 21, 264, 4, 1, 0, 72, 44, 28, 1.64, 12, 1, '2025-12-05 18:01:37'),
(103, 21, 265, 2, 3, 0, 63, 66, -3, 0.95, 6, 4, '2025-12-05 18:01:37'),
(104, 21, 266, 1, 2, 0, 32, 42, -10, 0.76, 3, 6, '2025-12-05 17:57:07'),
(105, 22, 267, 3, 0, 0, 45, 32, 13, 1.41, 9, 1, '2025-12-05 18:09:32'),
(106, 22, 268, 2, 1, 0, 40, 29, 11, 1.38, 6, 2, '2025-12-05 18:09:38'),
(107, 22, 269, 0, 3, 0, 31, 45, -14, 0.69, 0, 4, '2025-12-05 18:09:38'),
(108, 22, 270, 1, 2, 0, 32, 42, -10, 0.76, 3, 3, '2025-12-05 18:09:32');

-- --------------------------------------------------------

--
-- Estrutura para tabela `torneio_grupos`
--

CREATE TABLE `torneio_grupos` (
  `id` int(11) NOT NULL,
  `torneio_id` int(11) NOT NULL,
  `nome` varchar(10) NOT NULL COMMENT 'Nome do grupo (A, B, C, etc.)',
  `ordem` int(11) DEFAULT 1 COMMENT 'Ordem de exibição do grupo',
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Grupos do torneio (A, B, C, etc.)';

--
-- Despejando dados para a tabela `torneio_grupos`
--

INSERT INTO `torneio_grupos` (`id`, `torneio_id`, `nome`, `ordem`, `data_criacao`) VALUES
(29, 21, 'Chave 1', 1, '2025-12-05 17:52:28'),
(30, 21, 'Chave 2', 2, '2025-12-05 17:52:28');

-- --------------------------------------------------------

--
-- Estrutura para tabela `torneio_grupo_times`
--

CREATE TABLE `torneio_grupo_times` (
  `id` int(11) NOT NULL,
  `grupo_id` int(11) NOT NULL,
  `time_id` int(11) NOT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Associação entre grupos e times';

--
-- Despejando dados para a tabela `torneio_grupo_times`
--

INSERT INTO `torneio_grupo_times` (`id`, `grupo_id`, `time_id`, `data_criacao`) VALUES
(153, 29, 259, '2025-12-05 17:52:28'),
(154, 29, 260, '2025-12-05 17:52:28'),
(155, 29, 261, '2025-12-05 17:52:28'),
(156, 29, 262, '2025-12-05 17:52:28'),
(157, 30, 263, '2025-12-05 17:52:28'),
(158, 30, 264, '2025-12-05 17:52:28'),
(159, 30, 265, '2025-12-05 17:52:28'),
(160, 30, 266, '2025-12-05 17:52:28');

-- --------------------------------------------------------

--
-- Estrutura para tabela `torneio_jogos`
--

CREATE TABLE `torneio_jogos` (
  `id` int(11) NOT NULL,
  `torneio_id` int(11) NOT NULL,
  `grupo_id` int(11) DEFAULT NULL,
  `modalidade` varchar(50) DEFAULT 'todos_contra_todos',
  `vencedor_id` int(11) DEFAULT NULL,
  `data_jogo` datetime DEFAULT NULL,
  `status` enum('Próximo','Agendado','Em Andamento','Finalizado') DEFAULT 'Próximo',
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_atualizacao` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `torneio_jogo_times`
--

CREATE TABLE `torneio_jogo_times` (
  `id` int(11) NOT NULL,
  `jogo_id` int(11) NOT NULL,
  `time_id` int(11) NOT NULL,
  `pontos` int(11) DEFAULT NULL,
  `ordem` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Relaciona jogos do torneio com seus times e pontuações';

-- --------------------------------------------------------

--
-- Estrutura para tabela `torneio_modalidades`
--

CREATE TABLE `torneio_modalidades` (
  `id` int(11) NOT NULL,
  `torneio_id` int(11) NOT NULL,
  `todos_contra_todos` tinyint(1) DEFAULT 0,
  `grupos` tinyint(1) DEFAULT 0,
  `oitavas_final` tinyint(1) DEFAULT 0,
  `quartas_final` tinyint(1) DEFAULT 0,
  `semi_final` tinyint(1) DEFAULT 0,
  `terceiro_lugar` tinyint(1) DEFAULT 0,
  `final` tinyint(1) DEFAULT 0,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_atualizacao` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `torneio_participantes`
--

CREATE TABLE `torneio_participantes` (
  `id` int(11) NOT NULL,
  `torneio_id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `nome_avulso` varchar(100) DEFAULT NULL,
  `ordem` int(11) DEFAULT 0,
  `data_inscricao` timestamp NOT NULL DEFAULT current_timestamp(),
  `posicao_final` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `torneio_participantes`
--

INSERT INTO `torneio_participantes` (`id`, `torneio_id`, `usuario_id`, `nome_avulso`, `ordem`, `data_inscricao`, `posicao_final`) VALUES
(250, 21, 37, NULL, 1, '2025-12-05 17:17:34', NULL),
(251, 21, NULL, 'Lúcio', 2, '2025-12-05 17:17:53', NULL),
(252, 21, NULL, 'Marina', 3, '2025-12-05 17:17:53', NULL),
(253, 21, NULL, 'Valmir', 4, '2025-12-05 17:17:53', NULL),
(254, 21, NULL, 'Jéssica', 5, '2025-12-05 17:17:53', NULL),
(255, 21, NULL, 'Renato', 6, '2025-12-05 17:17:53', NULL),
(256, 21, NULL, 'Bianca', 7, '2025-12-05 17:17:53', NULL),
(257, 21, NULL, 'César', 8, '2025-12-05 17:17:53', NULL),
(258, 21, NULL, 'Tainá', 9, '2025-12-05 17:17:53', NULL),
(259, 21, NULL, 'Mauro', 10, '2025-12-05 17:17:53', NULL),
(260, 21, NULL, 'Patrícia', 11, '2025-12-05 17:17:53', NULL),
(261, 21, NULL, 'Álvaro', 12, '2025-12-05 17:17:53', NULL),
(262, 21, NULL, 'Camila', 13, '2025-12-05 17:17:53', NULL),
(263, 21, NULL, 'Edson', 14, '2025-12-05 17:17:53', NULL),
(264, 21, NULL, 'Larissa', 15, '2025-12-05 17:17:53', NULL),
(265, 21, NULL, 'Fábio', 16, '2025-12-05 17:17:53', NULL),
(266, 21, NULL, 'Viviane', 17, '2025-12-05 17:17:53', NULL),
(267, 21, NULL, 'Ricardo', 18, '2025-12-05 17:17:53', NULL),
(268, 21, NULL, 'Tereza', 19, '2025-12-05 17:18:16', NULL),
(269, 21, NULL, 'Daniel', 20, '2025-12-05 17:18:16', NULL),
(270, 21, NULL, 'Natália', 21, '2025-12-05 17:18:16', NULL),
(271, 21, NULL, 'Gustavo', 22, '2025-12-05 17:18:16', NULL),
(272, 21, NULL, 'João', 23, '2025-12-05 17:18:16', NULL),
(273, 21, NULL, 'Sabrina', 24, '2025-12-05 17:18:16', NULL),
(274, 21, NULL, 'Ícaro', 25, '2025-12-05 17:18:16', NULL),
(275, 21, NULL, 'Melissa', 26, '2025-12-05 17:18:16', NULL),
(276, 21, NULL, 'Raul', 27, '2025-12-05 17:18:16', NULL),
(277, 21, NULL, 'Sâmia', 28, '2025-12-05 17:18:16', NULL),
(278, 21, NULL, 'Breno', 29, '2025-12-05 17:18:16', NULL),
(279, 21, NULL, 'Eloá', 30, '2025-12-05 17:18:16', NULL),
(280, 21, NULL, 'Henrique', 31, '2025-12-05 17:18:16', NULL),
(281, 21, NULL, 'Taís', 32, '2025-12-05 17:18:16', NULL),
(282, 22, 36, NULL, 1, '2025-12-05 18:05:51', NULL),
(283, 22, 29, NULL, 2, '2025-12-05 18:05:51', NULL),
(284, 22, 35, NULL, 3, '2025-12-05 18:05:51', NULL),
(285, 22, 22, NULL, 4, '2025-12-05 18:05:51', NULL),
(286, 22, 21, NULL, 5, '2025-12-05 18:05:51', NULL),
(287, 22, 28, NULL, 6, '2025-12-05 18:05:51', NULL),
(288, 22, 27, NULL, 7, '2025-12-05 18:05:51', NULL),
(289, 22, 34, NULL, 8, '2025-12-05 18:05:51', NULL),
(290, 22, 2, NULL, 9, '2025-12-05 18:05:51', NULL),
(291, 22, 31, NULL, 10, '2025-12-05 18:05:51', NULL),
(292, 22, 23, NULL, 11, '2025-12-05 18:05:51', NULL),
(293, 22, 25, NULL, 12, '2025-12-05 18:05:51', NULL),
(294, 22, 39, NULL, 13, '2025-12-05 18:05:51', NULL),
(295, 22, 24, NULL, 14, '2025-12-05 18:05:51', NULL),
(296, 22, 33, NULL, 15, '2025-12-05 18:05:51', NULL),
(297, 22, 37, NULL, 16, '2025-12-05 18:05:51', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `torneio_partidas`
--

CREATE TABLE `torneio_partidas` (
  `id` int(11) NOT NULL,
  `torneio_id` int(11) NOT NULL,
  `time1_id` int(11) NOT NULL,
  `time2_id` int(11) NOT NULL,
  `fase` enum('Grupos','Quartas','Semi','Final','3º Lugar') DEFAULT 'Grupos',
  `rodada` int(11) DEFAULT 1 COMMENT 'Número da rodada na fase',
  `grupo_id` int(11) DEFAULT NULL COMMENT 'ID do grupo (para modalidade todos_chaves)',
  `pontos_time1` int(11) DEFAULT 0,
  `pontos_time2` int(11) DEFAULT 0,
  `vencedor_id` int(11) DEFAULT NULL,
  `data_partida` datetime DEFAULT NULL,
  `status` enum('Agendada','Em Andamento','Finalizada') DEFAULT 'Agendada',
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `torneio_partidas`
--

INSERT INTO `torneio_partidas` (`id`, `torneio_id`, `time1_id`, `time2_id`, `fase`, `rodada`, `grupo_id`, `pontos_time1`, `pontos_time2`, `vencedor_id`, `data_partida`, `status`, `data_criacao`) VALUES
(137, 21, 259, 260, 'Grupos', 1, 29, 12, 15, 260, NULL, 'Finalizada', '2025-12-05 17:52:28'),
(138, 21, 261, 262, 'Grupos', 1, 29, 15, 12, 261, NULL, 'Finalizada', '2025-12-05 17:52:28'),
(139, 21, 259, 261, 'Grupos', 2, 29, 8, 15, 261, NULL, 'Finalizada', '2025-12-05 17:52:28'),
(140, 21, 260, 262, 'Grupos', 2, 29, 15, 9, 260, NULL, 'Finalizada', '2025-12-05 17:52:28'),
(141, 21, 259, 262, 'Grupos', 3, 29, 15, 13, 259, NULL, 'Finalizada', '2025-12-05 17:52:28'),
(142, 21, 260, 261, 'Grupos', 3, 29, 12, 15, 261, NULL, 'Finalizada', '2025-12-05 17:52:28'),
(143, 21, 263, 264, 'Grupos', 1, 30, 5, 15, 264, NULL, 'Finalizada', '2025-12-05 17:52:28'),
(144, 21, 265, 266, 'Grupos', 1, 30, 15, 12, 265, NULL, 'Finalizada', '2025-12-05 17:52:28'),
(145, 21, 263, 265, 'Grupos', 2, 30, 9, 15, 265, NULL, 'Finalizada', '2025-12-05 17:52:28'),
(146, 21, 264, 266, 'Grupos', 2, 30, 15, 5, 264, NULL, 'Finalizada', '2025-12-05 17:52:28'),
(147, 21, 263, 266, 'Grupos', 3, 30, 12, 15, 266, NULL, 'Finalizada', '2025-12-05 17:52:28'),
(148, 21, 264, 265, 'Grupos', 3, 30, 15, 6, 264, NULL, 'Finalizada', '2025-12-05 17:52:28'),
(149, 22, 267, 268, 'Grupos', 1, NULL, 15, 10, 267, NULL, 'Finalizada', '2025-12-05 18:08:09'),
(150, 22, 269, 270, 'Grupos', 1, NULL, 12, 15, 270, NULL, 'Finalizada', '2025-12-05 18:08:09'),
(151, 22, 267, 269, 'Grupos', 2, NULL, 15, 10, 267, NULL, 'Finalizada', '2025-12-05 18:08:09'),
(152, 22, 268, 270, 'Grupos', 2, NULL, 15, 5, 268, NULL, 'Finalizada', '2025-12-05 18:08:09'),
(153, 22, 267, 270, 'Grupos', 3, NULL, 15, 12, 267, NULL, 'Finalizada', '2025-12-05 18:08:09'),
(154, 22, 268, 269, 'Grupos', 3, NULL, 15, 9, 268, NULL, 'Finalizada', '2025-12-05 18:08:09');

-- --------------------------------------------------------

--
-- Estrutura para tabela `torneio_solicitacoes`
--

CREATE TABLE `torneio_solicitacoes` (
  `id` int(11) NOT NULL,
  `torneio_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `status` enum('Pendente','Aprovada','Aceita','Rejeitada') DEFAULT 'Pendente',
  `data_solicitacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_resposta` timestamp NULL DEFAULT NULL,
  `respondido_por` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `torneio_solicitacoes`
--

INSERT INTO `torneio_solicitacoes` (`id`, `torneio_id`, `usuario_id`, `status`, `data_solicitacao`, `data_resposta`, `respondido_por`) VALUES
(4, 21, 37, 'Aprovada', '2025-12-05 17:17:19', '2025-12-05 17:17:34', 21);

-- --------------------------------------------------------

--
-- Estrutura para tabela `torneio_times`
--

CREATE TABLE `torneio_times` (
  `id` int(11) NOT NULL,
  `torneio_id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL,
  `cor` varchar(20) DEFAULT '#007bff',
  `ordem` int(11) DEFAULT 0,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `torneio_times`
--

INSERT INTO `torneio_times` (`id`, `torneio_id`, `nome`, `cor`, `ordem`, `data_criacao`) VALUES
(259, 21, 'Time 1', '#007bff', 1, '2025-12-05 17:26:23'),
(260, 21, 'Time 2', '#28a745', 2, '2025-12-05 17:26:23'),
(261, 21, 'Time 3', '#dc3545', 3, '2025-12-05 17:26:23'),
(262, 21, 'Time 4', '#ffc107', 4, '2025-12-05 17:26:23'),
(263, 21, 'Time 5', '#17a2b8', 5, '2025-12-05 17:26:23'),
(264, 21, 'Time 6', '#6f42c1', 6, '2025-12-05 17:26:23'),
(265, 21, 'Time 7', '#e83e8c', 7, '2025-12-05 17:26:23'),
(266, 21, 'Time 8', '#fd7e14', 8, '2025-12-05 17:26:23'),
(267, 22, 'Time 1', '#007bff', 1, '2025-12-05 18:05:58'),
(268, 22, 'Time 2', '#28a745', 2, '2025-12-05 18:05:58'),
(269, 22, 'Time 3', '#dc3545', 3, '2025-12-05 18:05:58'),
(270, 22, 'Time 4', '#ffc107', 4, '2025-12-05 18:05:58');

-- --------------------------------------------------------

--
-- Estrutura para tabela `torneio_time_integrantes`
--

CREATE TABLE `torneio_time_integrantes` (
  `id` int(11) NOT NULL,
  `time_id` int(11) NOT NULL,
  `participante_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `torneio_time_integrantes`
--

INSERT INTO `torneio_time_integrantes` (`id`, `time_id`, `participante_id`) VALUES
(820, 259, 256),
(819, 259, 268),
(821, 259, 276),
(822, 259, 281),
(825, 260, 250),
(824, 260, 263),
(823, 260, 277),
(849, 260, 279),
(828, 261, 254),
(829, 261, 259),
(826, 261, 260),
(827, 261, 267),
(833, 262, 261),
(832, 262, 262),
(830, 262, 271),
(831, 262, 274),
(850, 263, 251),
(835, 263, 257),
(836, 263, 265),
(834, 263, 278),
(839, 264, 252),
(837, 264, 253),
(840, 264, 255),
(838, 264, 269),
(841, 265, 270),
(842, 265, 272),
(843, 265, 273),
(844, 265, 280),
(845, 266, 258),
(847, 266, 264),
(848, 266, 266),
(846, 266, 275),
(901, 267, 283),
(904, 267, 294),
(902, 267, 296),
(903, 267, 297),
(905, 268, 286),
(906, 268, 288),
(907, 268, 289),
(908, 268, 292),
(909, 269, 282),
(910, 269, 287),
(911, 269, 290),
(912, 269, 291),
(913, 270, 284),
(914, 270, 285),
(915, 270, 293),
(916, 270, 295);

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `cpf` varchar(14) DEFAULT NULL,
  `telefone` varchar(15) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `nivel` enum('Iniciante','Intermediário','Avançado','Profissional') NOT NULL,
  `genero` enum('Masculino','Feminino') NOT NULL DEFAULT 'Masculino',
  `disponibilidade` text DEFAULT NULL,
  `data_aniversario` date DEFAULT NULL,
  `reputacao` int(11) DEFAULT 0,
  `foto_perfil` varchar(255) DEFAULT NULL,
  `data_cadastro` timestamp NOT NULL DEFAULT current_timestamp(),
  `ativo` tinyint(1) DEFAULT 1,
  `is_premium` tinyint(1) DEFAULT 0,
  `premium_ativado_em` timestamp NULL DEFAULT NULL,
  `premium_expira_em` timestamp NULL DEFAULT NULL,
  `ultimo_pagamento` timestamp NULL DEFAULT NULL,
  `is_admin` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome`, `usuario`, `cpf`, `telefone`, `email`, `senha`, `nivel`, `genero`, `disponibilidade`, `data_aniversario`, `reputacao`, `foto_perfil`, `data_cadastro`, `ativo`, `is_premium`, `premium_ativado_em`, `premium_expira_em`, `ultimo_pagamento`, `is_admin`) VALUES
(1, 'administrador', 'admin', '03032845017', '55991773439', 'admin@gmail.com', '$2y$10$p58DLcET.rA4fv7y/AT4SOmNtfHw9NMiXXWm2QC.1GttD0.dLWjc2', 'Profissional', 'Masculino', 'Finais de semana', NULL, 100, 'assets/arquivos/logousers/1.png', '2025-10-29 17:49:23', 1, 1, NULL, NULL, NULL, 3),
(2, 'Josep', 'josep', NULL, '', 'josep@gmail.com', '$2y$10$dlBB3lkXJUuM7y9NaNbuEePAr/R4.MiHKaLwmkmyoKLeTe0i2jaYW', 'Iniciante', 'Masculino', 'sempre quando da', NULL, 100, 'assets/arquivos/logousers/2.png', '2025-10-29 18:02:51', 1, 0, NULL, NULL, NULL, 0),
(21, 'Eduardo Gaier', 'eduardo', NULL, NULL, 'eduardo@gmail.com', '$2y$10$1tJci0JBDXIJp8t6PloT0.pGIFsiYOqvKAeHDpy/MS548jiiJNwpO', 'Intermediário', 'Masculino', '', NULL, 100, 'assets/arquivos/logousers/21.png', '2025-10-29 21:22:37', 1, 1, NULL, '2026-01-04 06:36:27', NULL, 0),
(22, 'Caroline Claussen', 'carol', NULL, '', 'carol@gmail.com', '$2y$10$p58DLcET.rA4fv7y/AT4SOmNtfHw9NMiXXWm2QC.1GttD0.dLWjc2', 'Intermediário', 'Feminino', '', NULL, 100, 'assets/arquivos/logousers/22.png', '2025-10-29 21:22:37', 1, 0, NULL, NULL, NULL, 0),
(23, 'Maria Vitoria', 'Vitoria', NULL, '', 'vitoria@gmail.com', '$2y$10$pQQGlh6rPtxk3sEwx/gp4O04MdaS2zp8quvIycaB2meJuW6xDz596', 'Intermediário', 'Feminino', '', NULL, 100, 'assets/arquivos/logousers/23.png', '2025-10-29 21:22:37', 1, 0, NULL, '0000-00-00 00:00:00', NULL, 0),
(24, 'Pamela Claussen', 'Pamela', NULL, '', 'pamela@gmail.com', '$2y$10$rDM4Duemn5KZlCuueN6KXeOqSlHPKtyprIeEOrWxfABxtLMsxMJ6K', 'Avançado', 'Feminino', '', NULL, 100, 'assets/arquivos/logousers/24.png', '2025-10-29 21:22:37', 0, 0, NULL, NULL, NULL, 0),
(25, 'Myrella Claussen', 'Myrella', NULL, '', 'myrella@gmail.com', '$2y$10$kXKNtIBa5CklUUTIYglfcuofeOgFWicCSBZAJSlGn/VYBZMRx/Af2', 'Intermediário', 'Feminino', '', NULL, 100, 'assets/arquivos/logousers/25.png', '2025-10-29 21:22:37', 1, 0, NULL, NULL, NULL, 0),
(26, 'wagner moreira', 'wagner moreira', '03032845021', '55991773439', 'wagner@gmail.com', '$2y$10$NMYQPmAwo1f78BAhmgHGNe7XEcsRBt3SXn5Kqx/X5q4rZ81H5brF2', 'Intermediário', 'Masculino', 'segunda e quarta', NULL, 100, NULL, '2025-10-30 16:29:29', 1, 0, NULL, NULL, NULL, 0),
(27, 'Guilherme Amorin', 'guilherme', '03032845023', '55991773439', 'guilherme@gmail.com', '$2y$10$FnLGOg4MhEijABLi9AvcqO2KKJeEvawXAq5sMyn79ofi1VFyiRR1.', 'Intermediário', 'Masculino', 'dasds', NULL, 100, 'assets/arquivos/logousers/27.png', '2025-10-30 20:42:49', 1, 0, NULL, NULL, NULL, 0),
(28, 'gabriel machado', 'gabriel', '03032845045', '55991773435', 'gabriel@gmail.com', '$2y$10$Ew.zZ.9JOFh00Sj.GjwnAOX0k/YYtAdoxB4DRrB34RzZZkhekS81W', 'Intermediário', 'Masculino', 'sempre', NULL, 100, 'assets/arquivos/logousers/28.png', '2025-11-14 18:40:58', 1, 0, NULL, NULL, NULL, 0),
(29, 'Bruna Claussen', 'bruna', '03032845071', '55991773433', 'bruna@gmail.com', '$2y$10$uniRG6407H1ijThZtu4Uc.y230LPEQekStzPFc/TV1h0yz6KRNJsC', 'Iniciante', 'Feminino', 'qqqq', NULL, 100, 'assets/arquivos/logousers/29.png', '2025-11-14 18:48:34', 1, 0, NULL, NULL, NULL, 0),
(30, 'Willian Goulart', 'willian', '03032845020', '55991773439', 'willian@gmail.com', '$2y$10$4q9Ek4hme/SybBcPQe6SI.KTNeEsqVziDOEBv/cP/sCLPuGGupDEW', 'Intermediário', 'Masculino', '111', NULL, 100, 'assets/arquivos/logousers/30.png', '2025-11-14 18:49:04', 1, 0, NULL, NULL, NULL, 0),
(31, 'Maninho', 'maninho', '03032845014', '55991773439', 'maninho@gmail.com', '$2y$10$DG5uYOXDgKMktsy.jW1En.9pDUYuIU5Owwk6tIQkOqvqXVjZDkucO', 'Intermediário', 'Masculino', 'asd', NULL, 100, 'assets/arquivos/logousers/31.png', '2025-11-14 18:56:18', 1, 0, NULL, NULL, NULL, 0),
(33, 'Paula Rodrigues', 'paula', '03032845012', '55991773439', 'paula@gmail.com', '$2y$10$YDrnDpBARkGuUbtfElkG1uL6RGGOt6Gic9KtUD5TIyiTbjomJR2ve', 'Intermediário', 'Feminino', '1', NULL, 100, NULL, '2025-11-14 18:58:09', 1, 0, NULL, NULL, NULL, 0),
(34, 'Isa Rodrigues', 'isa', '03032845072', '55991773439', 'isa@gmail.com', '$2y$10$KRNiIOYAo0KQp/kjKc0syOTxhlQ8OtdcoScugrt9ucitKMIum62nO', 'Intermediário', 'Feminino', '1', NULL, 100, 'assets/arquivos/logousers/34.png', '2025-11-14 18:58:36', 1, 0, NULL, NULL, NULL, 0),
(35, 'Camila Dorneles', 'camila', '03032845073', '55991773439', 'camila@gmail.com', '$2y$10$LCbg1uB8rd9A/tndmUORpeldUaKh0cLIhNaEdmdJscT6XkxCKDQVG', 'Iniciante', 'Feminino', '1', NULL, 100, 'assets/arquivos/logousers/35.png', '2025-11-14 19:04:29', 1, 0, NULL, NULL, NULL, 0),
(36, 'Alisson Claussen', 'alisson', '03032845074', '55991773439', 'alisson@gmail.com', '$2y$10$3qYJcpMdWBTblP3Ikl6gieKprUP.U9UpJOKT4AYCmjQyX26iYQIW2', 'Intermediário', 'Masculino', '1', NULL, 100, 'assets/arquivos/logousers/36.png', '2025-11-14 19:04:57', 1, 0, NULL, NULL, NULL, 0),
(37, 'Pedro Rossato', 'pedro', '03032845078', '55991773439', 'pedro@gmail.com', '$2y$10$dNO63Ulf0jqwWsDQT29Keezz9Gfju/dmkVvuc4K/ivOL8D0ppuN.u', 'Iniciante', 'Masculino', '1', NULL, 100, 'assets/arquivos/logousers/37.png', '2025-11-14 19:57:47', 1, 0, NULL, NULL, NULL, 0),
(39, 'Natan', 'natan', '03032845079', '55991773439', 'natan@gmail.com', '$2y$10$OwC.Rr38eFk.OB3cpdwY7OaHbojYOkKDtrC.NfQ1f99TQNngWglye', 'Intermediário', 'Masculino', '1', NULL, 100, NULL, '2025-11-14 19:59:59', 1, 0, NULL, NULL, NULL, 0),
(40, 'Lucas cassol', 'lucas', NULL, '55991773435', 'lucas@gmail.com', '$2y$10$LSTBXkdv4B0LwhwWZQpeYOoL5rcQM3bdcrcNM4VRDsG9Sy6a.kIvW', 'Intermediário', 'Masculino', '1', NULL, 100, NULL, '2025-11-14 22:50:31', 1, 1, NULL, '2026-01-04 06:04:43', NULL, 0);

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `atualizacoes_site`
--
ALTER TABLE `atualizacoes_site`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `avaliacoes_reputacao`
--
ALTER TABLE `avaliacoes_reputacao`
  ADD PRIMARY KEY (`id`),
  ADD KEY `avaliador_id` (`avaliador_id`),
  ADD KEY `avaliado_id` (`avaliado_id`),
  ADD KEY `jogo_id` (`jogo_id`);

--
-- Índices de tabela `avisos`
--
ALTER TABLE `avisos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `grupo_id` (`grupo_id`),
  ADD KEY `jogo_id` (`jogo_id`),
  ADD KEY `torneio_id` (`torneio_id`),
  ADD KEY `criado_por` (`criado_por`);

--
-- Índices de tabela `confirmacoes_presenca`
--
ALTER TABLE `confirmacoes_presenca`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_confirmation` (`jogo_id`,`usuario_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `dicas`
--
ALTER TABLE `dicas`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `galeria_fotos`
--
ALTER TABLE `galeria_fotos`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `grupos`
--
ALTER TABLE `grupos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `administrador_id` (`administrador_id`);

--
-- Índices de tabela `grupo_membros`
--
ALTER TABLE `grupo_membros`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_member` (`grupo_id`,`usuario_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `jogos`
--
ALTER TABLE `jogos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_grupo_id` (`grupo_id`),
  ADD KEY `idx_criado_por` (`criado_por`);

--
-- Índices de tabela `logos_grupos`
--
ALTER TABLE `logos_grupos`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `loja_produtos`
--
ALTER TABLE `loja_produtos`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `notificacoes`
--
ALTER TABLE `notificacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_usuario` (`usuario_id`);

--
-- Índices de tabela `pagamentos`
--
ALTER TABLE `pagamentos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `pagamento_id` (`pagamento_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `partidas`
--
ALTER TABLE `partidas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jogo_id` (`jogo_id`),
  ADD KEY `time1_id` (`time1_id`),
  ADD KEY `time2_id` (`time2_id`);

--
-- Índices de tabela `profissionais`
--
ALTER TABLE `profissionais`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `quadras`
--
ALTER TABLE `quadras`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `sistemas_pontuacao`
--
ALTER TABLE `sistemas_pontuacao`
  ADD PRIMARY KEY (`id`),
  ADD KEY `grupo_id` (`grupo_id`);

--
-- Índices de tabela `sistema_pontuacao_jogos`
--
ALTER TABLE `sistema_pontuacao_jogos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sistema_id` (`sistema_id`);

--
-- Índices de tabela `sistema_pontuacao_participantes`
--
ALTER TABLE `sistema_pontuacao_participantes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_jogo_usuario` (`jogo_id`,`usuario_id`),
  ADD KEY `jogo_id` (`jogo_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `sistema_pontuacao_pontos`
--
ALTER TABLE `sistema_pontuacao_pontos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_jogo_usuario` (`jogo_id`,`usuario_id`),
  ADD KEY `jogo_id` (`jogo_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `sistema_pontuacao_times`
--
ALTER TABLE `sistema_pontuacao_times`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jogo_id` (`jogo_id`);

--
-- Índices de tabela `sistema_pontuacao_time_jogadores`
--
ALTER TABLE `sistema_pontuacao_time_jogadores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_time_usuario` (`time_id`,`usuario_id`),
  ADD KEY `time_id` (`time_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `times`
--
ALTER TABLE `times`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jogo_id` (`jogo_id`);

--
-- Índices de tabela `time_jogadores`
--
ALTER TABLE `time_jogadores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `time_id` (`time_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `torneios`
--
ALTER TABLE `torneios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `grupo_id` (`grupo_id`),
  ADD KEY `criado_por` (`criado_por`);

--
-- Índices de tabela `torneio_chaves`
--
ALTER TABLE `torneio_chaves`
  ADD PRIMARY KEY (`id`),
  ADD KEY `torneio_id` (`torneio_id`),
  ADD KEY `jogador1_id` (`jogador1_id`),
  ADD KEY `jogador2_id` (`jogador2_id`),
  ADD KEY `vencedor_id` (`vencedor_id`);

--
-- Índices de tabela `torneio_chaves_times`
--
ALTER TABLE `torneio_chaves_times`
  ADD PRIMARY KEY (`id`),
  ADD KEY `torneio_id` (`torneio_id`),
  ADD KEY `fase_chave` (`fase`,`chave_numero`),
  ADD KEY `time1_id` (`time1_id`),
  ADD KEY `time2_id` (`time2_id`),
  ADD KEY `vencedor_id` (`vencedor_id`);

--
-- Índices de tabela `torneio_classificacao`
--
ALTER TABLE `torneio_classificacao`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_torneio_time` (`torneio_id`,`time_id`),
  ADD KEY `torneio_id` (`torneio_id`),
  ADD KEY `time_id` (`time_id`),
  ADD KEY `posicao` (`posicao`);

--
-- Índices de tabela `torneio_grupos`
--
ALTER TABLE `torneio_grupos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `torneio_grupo_unico` (`torneio_id`,`nome`),
  ADD KEY `idx_torneio_id` (`torneio_id`);

--
-- Índices de tabela `torneio_grupo_times`
--
ALTER TABLE `torneio_grupo_times`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `grupo_time_unico` (`grupo_id`,`time_id`),
  ADD KEY `idx_grupo_id` (`grupo_id`),
  ADD KEY `idx_time_id` (`time_id`);

--
-- Índices de tabela `torneio_jogos`
--
ALTER TABLE `torneio_jogos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `torneio_id` (`torneio_id`),
  ADD KEY `idx_grupo_id` (`grupo_id`);

--
-- Índices de tabela `torneio_jogo_times`
--
ALTER TABLE `torneio_jogo_times`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `jogo_time_unique` (`jogo_id`,`time_id`),
  ADD KEY `jogo_id` (`jogo_id`),
  ADD KEY `time_id` (`time_id`);

--
-- Índices de tabela `torneio_modalidades`
--
ALTER TABLE `torneio_modalidades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `torneio_id` (`torneio_id`);

--
-- Índices de tabela `torneio_participantes`
--
ALTER TABLE `torneio_participantes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_participant` (`torneio_id`,`usuario_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `torneio_partidas`
--
ALTER TABLE `torneio_partidas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `torneio_id` (`torneio_id`),
  ADD KEY `time1_id` (`time1_id`),
  ADD KEY `time2_id` (`time2_id`),
  ADD KEY `vencedor_id` (`vencedor_id`),
  ADD KEY `fase_rodada` (`fase`,`rodada`),
  ADD KEY `idx_grupo_id` (`grupo_id`);

--
-- Índices de tabela `torneio_solicitacoes`
--
ALTER TABLE `torneio_solicitacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `torneio_id` (`torneio_id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `status` (`status`);

--
-- Índices de tabela `torneio_times`
--
ALTER TABLE `torneio_times`
  ADD PRIMARY KEY (`id`),
  ADD KEY `torneio_id` (`torneio_id`);

--
-- Índices de tabela `torneio_time_integrantes`
--
ALTER TABLE `torneio_time_integrantes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_time_participante` (`time_id`,`participante_id`),
  ADD KEY `time_id` (`time_id`),
  ADD KEY `participante_id` (`participante_id`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario` (`usuario`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `cpf` (`cpf`),
  ADD KEY `idx_cpf` (`cpf`),
  ADD KEY `idx_telefone` (`telefone`),
  ADD KEY `idx_usuario` (`usuario`),
  ADD KEY `idx_is_premium` (`is_premium`),
  ADD KEY `idx_is_admin` (`is_admin`),
  ADD KEY `idx_premium_expira` (`premium_expira_em`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `atualizacoes_site`
--
ALTER TABLE `atualizacoes_site`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `avaliacoes_reputacao`
--
ALTER TABLE `avaliacoes_reputacao`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `avisos`
--
ALTER TABLE `avisos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `confirmacoes_presenca`
--
ALTER TABLE `confirmacoes_presenca`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT de tabela `dicas`
--
ALTER TABLE `dicas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `galeria_fotos`
--
ALTER TABLE `galeria_fotos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `grupos`
--
ALTER TABLE `grupos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de tabela `grupo_membros`
--
ALTER TABLE `grupo_membros`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT de tabela `jogos`
--
ALTER TABLE `jogos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT de tabela `logos_grupos`
--
ALTER TABLE `logos_grupos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de tabela `loja_produtos`
--
ALTER TABLE `loja_produtos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `notificacoes`
--
ALTER TABLE `notificacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT de tabela `pagamentos`
--
ALTER TABLE `pagamentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `partidas`
--
ALTER TABLE `partidas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `profissionais`
--
ALTER TABLE `profissionais`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `quadras`
--
ALTER TABLE `quadras`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `sistemas_pontuacao`
--
ALTER TABLE `sistemas_pontuacao`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `sistema_pontuacao_jogos`
--
ALTER TABLE `sistema_pontuacao_jogos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `sistema_pontuacao_participantes`
--
ALTER TABLE `sistema_pontuacao_participantes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT de tabela `sistema_pontuacao_pontos`
--
ALTER TABLE `sistema_pontuacao_pontos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=74;

--
-- AUTO_INCREMENT de tabela `sistema_pontuacao_times`
--
ALTER TABLE `sistema_pontuacao_times`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `sistema_pontuacao_time_jogadores`
--
ALTER TABLE `sistema_pontuacao_time_jogadores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `times`
--
ALTER TABLE `times`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `time_jogadores`
--
ALTER TABLE `time_jogadores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `torneios`
--
ALTER TABLE `torneios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT de tabela `torneio_chaves`
--
ALTER TABLE `torneio_chaves`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `torneio_chaves_times`
--
ALTER TABLE `torneio_chaves_times`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `torneio_classificacao`
--
ALTER TABLE `torneio_classificacao`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=109;

--
-- AUTO_INCREMENT de tabela `torneio_grupos`
--
ALTER TABLE `torneio_grupos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT de tabela `torneio_grupo_times`
--
ALTER TABLE `torneio_grupo_times`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=161;

--
-- AUTO_INCREMENT de tabela `torneio_jogos`
--
ALTER TABLE `torneio_jogos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1008;

--
-- AUTO_INCREMENT de tabela `torneio_jogo_times`
--
ALTER TABLE `torneio_jogo_times`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2003;

--
-- AUTO_INCREMENT de tabela `torneio_modalidades`
--
ALTER TABLE `torneio_modalidades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de tabela `torneio_participantes`
--
ALTER TABLE `torneio_participantes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=298;

--
-- AUTO_INCREMENT de tabela `torneio_partidas`
--
ALTER TABLE `torneio_partidas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=155;

--
-- AUTO_INCREMENT de tabela `torneio_solicitacoes`
--
ALTER TABLE `torneio_solicitacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `torneio_times`
--
ALTER TABLE `torneio_times`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=271;

--
-- AUTO_INCREMENT de tabela `torneio_time_integrantes`
--
ALTER TABLE `torneio_time_integrantes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=917;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `avaliacoes_reputacao`
--
ALTER TABLE `avaliacoes_reputacao`
  ADD CONSTRAINT `avaliacoes_reputacao_ibfk_1` FOREIGN KEY (`avaliador_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `avaliacoes_reputacao_ibfk_2` FOREIGN KEY (`avaliado_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `avaliacoes_reputacao_ibfk_3` FOREIGN KEY (`jogo_id`) REFERENCES `jogos` (`id`);

--
-- Restrições para tabelas `avisos`
--
ALTER TABLE `avisos`
  ADD CONSTRAINT `avisos_ibfk_1` FOREIGN KEY (`grupo_id`) REFERENCES `grupos` (`id`),
  ADD CONSTRAINT `avisos_ibfk_2` FOREIGN KEY (`jogo_id`) REFERENCES `jogos` (`id`),
  ADD CONSTRAINT `avisos_ibfk_3` FOREIGN KEY (`torneio_id`) REFERENCES `torneios` (`id`),
  ADD CONSTRAINT `avisos_ibfk_4` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `confirmacoes_presenca`
--
ALTER TABLE `confirmacoes_presenca`
  ADD CONSTRAINT `confirmacoes_presenca_ibfk_1` FOREIGN KEY (`jogo_id`) REFERENCES `jogos` (`id`),
  ADD CONSTRAINT `confirmacoes_presenca_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `grupos`
--
ALTER TABLE `grupos`
  ADD CONSTRAINT `grupos_ibfk_1` FOREIGN KEY (`administrador_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `grupo_membros`
--
ALTER TABLE `grupo_membros`
  ADD CONSTRAINT `grupo_membros_ibfk_1` FOREIGN KEY (`grupo_id`) REFERENCES `grupos` (`id`),
  ADD CONSTRAINT `grupo_membros_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `jogos`
--
ALTER TABLE `jogos`
  ADD CONSTRAINT `fk_jogos_grupos` FOREIGN KEY (`grupo_id`) REFERENCES `grupos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_jogos_usuarios` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `jogos_ibfk_2` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `pagamentos`
--
ALTER TABLE `pagamentos`
  ADD CONSTRAINT `pagamentos_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `partidas`
--
ALTER TABLE `partidas`
  ADD CONSTRAINT `partidas_ibfk_1` FOREIGN KEY (`jogo_id`) REFERENCES `jogos` (`id`),
  ADD CONSTRAINT `partidas_ibfk_2` FOREIGN KEY (`time1_id`) REFERENCES `times` (`id`),
  ADD CONSTRAINT `partidas_ibfk_3` FOREIGN KEY (`time2_id`) REFERENCES `times` (`id`);

--
-- Restrições para tabelas `sistemas_pontuacao`
--
ALTER TABLE `sistemas_pontuacao`
  ADD CONSTRAINT `sistemas_pontuacao_ibfk_1` FOREIGN KEY (`grupo_id`) REFERENCES `grupos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `sistema_pontuacao_jogos`
--
ALTER TABLE `sistema_pontuacao_jogos`
  ADD CONSTRAINT `sistema_pontuacao_jogos_ibfk_1` FOREIGN KEY (`sistema_id`) REFERENCES `sistemas_pontuacao` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `sistema_pontuacao_participantes`
--
ALTER TABLE `sistema_pontuacao_participantes`
  ADD CONSTRAINT `sistema_pontuacao_participantes_ibfk_1` FOREIGN KEY (`jogo_id`) REFERENCES `sistema_pontuacao_jogos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sistema_pontuacao_participantes_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `sistema_pontuacao_pontos`
--
ALTER TABLE `sistema_pontuacao_pontos`
  ADD CONSTRAINT `sistema_pontuacao_pontos_ibfk_1` FOREIGN KEY (`jogo_id`) REFERENCES `sistema_pontuacao_jogos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sistema_pontuacao_pontos_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `sistema_pontuacao_times`
--
ALTER TABLE `sistema_pontuacao_times`
  ADD CONSTRAINT `sistema_pontuacao_times_ibfk_1` FOREIGN KEY (`jogo_id`) REFERENCES `sistema_pontuacao_jogos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `sistema_pontuacao_time_jogadores`
--
ALTER TABLE `sistema_pontuacao_time_jogadores`
  ADD CONSTRAINT `sistema_pontuacao_time_jogadores_ibfk_1` FOREIGN KEY (`time_id`) REFERENCES `sistema_pontuacao_times` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sistema_pontuacao_time_jogadores_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `times`
--
ALTER TABLE `times`
  ADD CONSTRAINT `times_ibfk_1` FOREIGN KEY (`jogo_id`) REFERENCES `jogos` (`id`);

--
-- Restrições para tabelas `time_jogadores`
--
ALTER TABLE `time_jogadores`
  ADD CONSTRAINT `time_jogadores_ibfk_1` FOREIGN KEY (`time_id`) REFERENCES `times` (`id`),
  ADD CONSTRAINT `time_jogadores_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `torneios`
--
ALTER TABLE `torneios`
  ADD CONSTRAINT `torneios_ibfk_1` FOREIGN KEY (`grupo_id`) REFERENCES `grupos` (`id`),
  ADD CONSTRAINT `torneios_ibfk_2` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `torneio_chaves`
--
ALTER TABLE `torneio_chaves`
  ADD CONSTRAINT `torneio_chaves_ibfk_1` FOREIGN KEY (`torneio_id`) REFERENCES `torneios` (`id`),
  ADD CONSTRAINT `torneio_chaves_ibfk_2` FOREIGN KEY (`jogador1_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `torneio_chaves_ibfk_3` FOREIGN KEY (`jogador2_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `torneio_chaves_ibfk_4` FOREIGN KEY (`vencedor_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `torneio_chaves_times`
--
ALTER TABLE `torneio_chaves_times`
  ADD CONSTRAINT `torneio_chaves_times_ibfk_1` FOREIGN KEY (`torneio_id`) REFERENCES `torneios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `torneio_chaves_times_ibfk_2` FOREIGN KEY (`time1_id`) REFERENCES `torneio_times` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `torneio_chaves_times_ibfk_3` FOREIGN KEY (`time2_id`) REFERENCES `torneio_times` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `torneio_chaves_times_ibfk_4` FOREIGN KEY (`vencedor_id`) REFERENCES `torneio_times` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `torneio_classificacao`
--
ALTER TABLE `torneio_classificacao`
  ADD CONSTRAINT `torneio_classificacao_ibfk_1` FOREIGN KEY (`torneio_id`) REFERENCES `torneios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `torneio_classificacao_ibfk_2` FOREIGN KEY (`time_id`) REFERENCES `torneio_times` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `torneio_grupos`
--
ALTER TABLE `torneio_grupos`
  ADD CONSTRAINT `fk_torneio_grupos_torneio` FOREIGN KEY (`torneio_id`) REFERENCES `torneios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `torneio_grupo_times`
--
ALTER TABLE `torneio_grupo_times`
  ADD CONSTRAINT `fk_torneio_grupo_times_grupo` FOREIGN KEY (`grupo_id`) REFERENCES `torneio_grupos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_torneio_grupo_times_time` FOREIGN KEY (`time_id`) REFERENCES `torneio_times` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `torneio_jogos`
--
ALTER TABLE `torneio_jogos`
  ADD CONSTRAINT `torneio_jogos_ibfk_1` FOREIGN KEY (`torneio_id`) REFERENCES `torneios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `torneio_jogo_times`
--
ALTER TABLE `torneio_jogo_times`
  ADD CONSTRAINT `torneio_jogo_times_ibfk_1` FOREIGN KEY (`jogo_id`) REFERENCES `torneio_jogos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `torneio_jogo_times_ibfk_2` FOREIGN KEY (`time_id`) REFERENCES `torneio_times` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `torneio_modalidades`
--
ALTER TABLE `torneio_modalidades`
  ADD CONSTRAINT `torneio_modalidades_ibfk_1` FOREIGN KEY (`torneio_id`) REFERENCES `torneios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `torneio_participantes`
--
ALTER TABLE `torneio_participantes`
  ADD CONSTRAINT `torneio_participantes_ibfk_1` FOREIGN KEY (`torneio_id`) REFERENCES `torneios` (`id`),
  ADD CONSTRAINT `torneio_participantes_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `torneio_partidas`
--
ALTER TABLE `torneio_partidas`
  ADD CONSTRAINT `torneio_partidas_ibfk_1` FOREIGN KEY (`torneio_id`) REFERENCES `torneios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `torneio_partidas_ibfk_2` FOREIGN KEY (`time1_id`) REFERENCES `torneio_times` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `torneio_partidas_ibfk_3` FOREIGN KEY (`time2_id`) REFERENCES `torneio_times` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `torneio_partidas_ibfk_4` FOREIGN KEY (`vencedor_id`) REFERENCES `torneio_times` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `torneio_times`
--
ALTER TABLE `torneio_times`
  ADD CONSTRAINT `torneio_times_ibfk_1` FOREIGN KEY (`torneio_id`) REFERENCES `torneios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `torneio_time_integrantes`
--
ALTER TABLE `torneio_time_integrantes`
  ADD CONSTRAINT `torneio_time_integrantes_ibfk_1` FOREIGN KEY (`time_id`) REFERENCES `torneio_times` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `torneio_time_integrantes_ibfk_2` FOREIGN KEY (`participante_id`) REFERENCES `torneio_participantes` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
