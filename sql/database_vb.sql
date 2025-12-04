-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 04/12/2025 às 20:28
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
(34, 25, 21, 'Confirmado', '2025-11-25 19:40:20', NULL);

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
(25, 15, 'jogoo bora', 'xffxgdf', '2025-11-25 16:40:00', '2025-11-26 16:40:00', '8000', 6, 6, 'Finalizado', 21, '2025-11-25 19:40:20', 'Volei', '55991773439');

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
(2, 'Joelheira', 'Protege os joelhos contra impactos.', 55.00, 'assets/arquivos/produtos/6917b9e87414f_1763162600.jpg', 1, '2025-11-14 23:23:20', NULL);

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
(48, 35, 'Solicitação de grupo aprovada', 'Sua entrada no grupo #15 foi aprovada.', 0, '2025-11-14 20:00:34');

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
  `status` enum('Agendada','Em Andamento','Finalizada') DEFAULT 'Agendada'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `inscricoes_abertas` tinyint(1) DEFAULT 0 COMMENT '1 = aberto para inscrições, 0 = fechado',
  `criado_por` int(11) NOT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `torneios`
--

INSERT INTO `torneios` (`id`, `nome`, `descricao`, `grupo_id`, `tipo`, `data_inicio`, `data_fim`, `max_participantes`, `quantidade_times`, `integrantes_por_time`, `modalidade`, `quantidade_grupos`, `status`, `inscricoes_abertas`, `criado_por`, `data_criacao`) VALUES
(20, 'Diretoria do Volley', NULL, 15, 'grupo', '2025-11-26 00:00:00', '2025-12-04 10:41:18', 16, 8, 2, 'todos_contra_todos', 2, 'Finalizado', 0, 21, '2025-11-25 19:41:25'),
(28, 'Rangers', NULL, 15, 'grupo', '2025-12-11 00:00:00', NULL, 16, 8, 2, 'todos_chaves', 2, 'Criado', 0, 21, '2025-12-04 14:11:13'),
(29, 'Aleatorios', NULL, 15, 'grupo', '2025-12-17 00:00:00', NULL, 16, 8, 2, NULL, NULL, 'Criado', 0, 21, '2025-12-04 17:22:28'),
(31, 'Verao 2026', NULL, NULL, 'avulso', '2025-12-31 00:00:00', NULL, 20, 5, 4, NULL, NULL, 'Criado', 0, 21, '2025-12-04 17:58:13');

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
(1, 28, 'Semi', 1, 222, 224, 224, 12, 15, NULL, 'Finalizada', '2025-12-04 14:33:46'),
(2, 28, 'Semi', 2, 226, 221, 221, 8, 15, NULL, 'Finalizada', '2025-12-04 14:33:46'),
(3, 28, 'Final', 1, 224, 221, 224, 15, 6, NULL, 'Finalizada', '2025-12-04 14:33:46'),
(4, 28, '3º Lugar', 1, 222, 226, 222, 8, 4, NULL, 'Finalizada', '2025-12-04 14:33:46');

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
(17, 20, 211, 2, 5, 0, 69, 74, -5, 0.93, 6, 6, '2025-12-04 13:02:59'),
(18, 20, 212, 2, 5, 0, 63, 70, -7, 0.90, 6, 7, '2025-12-04 13:03:06'),
(19, 20, 213, 3, 4, 0, 45, 51, -6, 0.88, 9, 5, '2025-12-04 13:03:11'),
(20, 20, 214, 4, 3, 0, 73, 70, 3, 1.04, 12, 3, '2025-12-04 13:03:17'),
(21, 20, 215, 4, 3, 0, 67, 62, 5, 1.08, 12, 2, '2025-12-04 13:03:17'),
(22, 20, 216, 7, 0, 0, 89, 54, 35, 1.65, 21, 1, '2025-12-04 13:03:11'),
(23, 20, 217, 2, 5, 0, 53, 72, -19, 0.74, 6, 8, '2025-12-04 13:03:06'),
(24, 20, 218, 4, 3, 0, 50, 56, -6, 0.89, 12, 4, '2025-12-04 13:03:17'),
(73, 28, 219, 1, 2, 0, 26, 26, 0, 1.00, 3, 6, '2025-12-04 14:23:52'),
(74, 28, 220, 0, 3, 0, 25, 35, -10, 0.71, 0, 7, '2025-12-04 14:23:25'),
(75, 28, 221, 2, 1, 0, 24, 22, 2, 1.09, 6, 4, '2025-12-04 14:23:52'),
(76, 28, 222, 3, 0, 0, 27, 19, 8, 1.42, 9, 1, '2025-12-04 14:23:04'),
(77, 28, 223, 1, 2, 0, 32, 29, 3, 1.10, 3, 5, '2025-12-04 14:23:52'),
(78, 28, 224, 2, 1, 0, 32, 29, 3, 1.10, 6, 3, '2025-12-04 14:23:52'),
(79, 28, 225, 0, 3, 0, 18, 32, -14, 0.56, 0, 8, '2025-12-04 14:23:52'),
(80, 28, 226, 3, 0, 0, 29, 21, 8, 1.38, 9, 2, '2025-12-04 14:23:45');

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
(21, 28, 'Chave 1', 1, '2025-12-04 14:18:50'),
(22, 28, 'Chave 2', 2, '2025-12-04 14:18:50');

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
(145, 21, 219, '2025-12-04 14:18:50'),
(146, 21, 220, '2025-12-04 14:18:50'),
(147, 21, 221, '2025-12-04 14:18:50'),
(148, 21, 222, '2025-12-04 14:18:50'),
(149, 22, 223, '2025-12-04 14:18:50'),
(150, 22, 224, '2025-12-04 14:18:50'),
(151, 22, 225, '2025-12-04 14:18:50'),
(152, 22, 226, '2025-12-04 14:18:50');

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
  `data_inscricao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `torneio_participantes`
--

INSERT INTO `torneio_participantes` (`id`, `torneio_id`, `usuario_id`, `nome_avulso`, `ordem`, `data_inscricao`) VALUES
(230, 20, 36, NULL, 1, '2025-11-25 19:46:52'),
(231, 20, 29, NULL, 2, '2025-11-25 19:46:52'),
(232, 20, 35, NULL, 3, '2025-11-25 19:46:52'),
(233, 20, 22, NULL, 4, '2025-11-25 19:46:52'),
(234, 20, 21, NULL, 5, '2025-11-25 19:46:52'),
(235, 20, 28, NULL, 6, '2025-11-25 19:46:52'),
(236, 20, 27, NULL, 7, '2025-11-25 19:46:52'),
(237, 20, 34, NULL, 8, '2025-11-25 19:46:52'),
(238, 20, 2, NULL, 9, '2025-11-25 19:46:52'),
(239, 20, 31, NULL, 10, '2025-11-25 19:46:52'),
(240, 20, 23, NULL, 11, '2025-11-25 19:46:52'),
(241, 20, 25, NULL, 12, '2025-11-25 19:46:52'),
(242, 20, 39, NULL, 13, '2025-11-25 19:46:52'),
(243, 20, 24, NULL, 14, '2025-11-25 19:46:52'),
(244, 20, 33, NULL, 15, '2025-11-25 19:46:52'),
(245, 20, 37, NULL, 16, '2025-11-25 19:46:52'),
(246, 28, 36, NULL, 1, '2025-12-04 14:11:58'),
(247, 28, 29, NULL, 2, '2025-12-04 14:11:58'),
(248, 28, 35, NULL, 3, '2025-12-04 14:11:58'),
(249, 28, 22, NULL, 4, '2025-12-04 14:11:58'),
(250, 28, 21, NULL, 5, '2025-12-04 14:11:58'),
(251, 28, 28, NULL, 6, '2025-12-04 14:11:58'),
(252, 28, 27, NULL, 7, '2025-12-04 14:11:58'),
(253, 28, 34, NULL, 8, '2025-12-04 14:11:58'),
(254, 28, 2, NULL, 9, '2025-12-04 14:11:58'),
(255, 28, 31, NULL, 10, '2025-12-04 14:11:58'),
(256, 28, 23, NULL, 11, '2025-12-04 14:11:58'),
(257, 28, 25, NULL, 12, '2025-12-04 14:11:58'),
(258, 28, 39, NULL, 13, '2025-12-04 14:11:58'),
(259, 28, 24, NULL, 14, '2025-12-04 14:11:58'),
(260, 28, 33, NULL, 15, '2025-12-04 14:11:58'),
(261, 28, 37, NULL, 16, '2025-12-04 14:11:58'),
(262, 29, 36, NULL, 1, '2025-12-04 17:22:55'),
(263, 29, 29, NULL, 2, '2025-12-04 17:22:55'),
(264, 29, 35, NULL, 3, '2025-12-04 17:22:55'),
(265, 29, 22, NULL, 4, '2025-12-04 17:22:55'),
(266, 29, 21, NULL, 5, '2025-12-04 17:22:55'),
(267, 29, 28, NULL, 6, '2025-12-04 17:22:55'),
(268, 29, 27, NULL, 7, '2025-12-04 17:22:55'),
(269, 29, 34, NULL, 8, '2025-12-04 17:22:55'),
(270, 29, 2, NULL, 9, '2025-12-04 17:22:55'),
(271, 29, 31, NULL, 10, '2025-12-04 17:22:55'),
(272, 29, 23, NULL, 11, '2025-12-04 17:22:55'),
(273, 29, 25, NULL, 12, '2025-12-04 17:22:55'),
(274, 29, 39, NULL, 13, '2025-12-04 17:22:55'),
(275, 29, 24, NULL, 14, '2025-12-04 17:22:55'),
(276, 29, 33, NULL, 15, '2025-12-04 17:22:55'),
(277, 29, 37, NULL, 16, '2025-12-04 17:22:55'),
(300, 31, NULL, 'Bruno', 2, '2025-12-04 18:00:09'),
(301, 31, NULL, 'Marcelo', 3, '2025-12-04 18:00:09'),
(302, 31, NULL, 'Diego', 4, '2025-12-04 18:00:09'),
(303, 31, NULL, 'Eduardo', 5, '2025-12-04 18:00:09'),
(304, 31, NULL, 'Henrique', 6, '2025-12-04 18:00:09'),
(305, 31, NULL, 'Gustavo', 7, '2025-12-04 18:00:09'),
(306, 31, NULL, 'André', 8, '2025-12-04 18:00:09'),
(307, 31, NULL, 'Leonardo', 9, '2025-12-04 18:00:09'),
(308, 31, NULL, 'Tiago', 10, '2025-12-04 18:00:09'),
(309, 31, NULL, 'Felipe', 11, '2025-12-04 18:00:09'),
(310, 31, NULL, 'Murilo', 12, '2025-12-04 18:00:09'),
(311, 31, NULL, 'Rodrigo', 13, '2025-12-04 18:00:09'),
(312, 31, NULL, 'Daniel', 14, '2025-12-04 18:00:09'),
(313, 31, NULL, 'Jonas', 15, '2025-12-04 18:00:09'),
(314, 31, NULL, 'Victor', 16, '2025-12-04 18:00:09'),
(315, 31, NULL, 'Samuel', 17, '2025-12-04 18:00:09'),
(316, 31, NULL, 'Caio', 18, '2025-12-04 18:00:09'),
(317, 31, NULL, 'Lucas', 19, '2025-12-04 18:00:09'),
(318, 31, 37, NULL, 20, '2025-12-04 18:14:26');

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
(41, 20, 211, 212, 'Grupos', 1, NULL, 20, 18, 211, NULL, 'Finalizada', '2025-12-04 12:44:29'),
(42, 20, 213, 214, 'Grupos', 1, NULL, 14, 18, 214, NULL, 'Finalizada', '2025-12-04 12:44:29'),
(43, 20, 215, 216, 'Grupos', 1, NULL, 15, 17, 216, NULL, 'Finalizada', '2025-12-04 12:44:29'),
(44, 20, 217, 218, 'Grupos', 1, NULL, 12, 16, 218, NULL, 'Finalizada', '2025-12-04 12:44:29'),
(45, 20, 211, 213, 'Grupos', 2, NULL, 14, 16, 213, NULL, 'Finalizada', '2025-12-04 12:44:29'),
(46, 20, 212, 214, 'Grupos', 2, NULL, 15, 16, 214, NULL, 'Finalizada', '2025-12-04 12:44:29'),
(47, 20, 215, 217, 'Grupos', 2, NULL, 21, 14, 215, NULL, 'Finalizada', '2025-12-04 12:44:29'),
(48, 20, 216, 218, 'Grupos', 2, NULL, 20, 8, 216, NULL, 'Finalizada', '2025-12-04 12:44:29'),
(49, 20, 211, 214, 'Grupos', 3, NULL, 12, 15, 214, NULL, 'Finalizada', '2025-12-04 12:44:29'),
(50, 20, 212, 213, 'Grupos', 3, NULL, 5, 2, 212, NULL, 'Finalizada', '2025-12-04 12:44:29'),
(51, 20, 215, 218, 'Grupos', 3, NULL, 7, 10, 218, NULL, 'Finalizada', '2025-12-04 12:44:29'),
(52, 20, 216, 217, 'Grupos', 3, NULL, 12, 7, 216, NULL, 'Finalizada', '2025-12-04 12:44:29'),
(53, 20, 211, 215, 'Grupos', 4, NULL, 8, 9, 215, NULL, 'Finalizada', '2025-12-04 12:44:29'),
(54, 20, 212, 216, 'Grupos', 4, NULL, 12, 17, 216, NULL, 'Finalizada', '2025-12-04 12:44:29'),
(55, 20, 213, 217, 'Grupos', 4, NULL, 4, 2, 213, NULL, 'Finalizada', '2025-12-04 12:44:29'),
(56, 20, 214, 218, 'Grupos', 4, NULL, 2, 3, 218, NULL, 'Finalizada', '2025-12-04 12:44:29'),
(57, 20, 211, 216, 'Grupos', 5, NULL, 8, 10, 216, NULL, 'Finalizada', '2025-12-04 12:44:29'),
(58, 20, 212, 215, 'Grupos', 5, NULL, 2, 4, 215, NULL, 'Finalizada', '2025-12-04 12:44:29'),
(59, 20, 213, 218, 'Grupos', 5, NULL, 4, 6, 218, NULL, 'Finalizada', '2025-12-04 12:44:29'),
(60, 20, 214, 217, 'Grupos', 5, NULL, 12, 8, 214, NULL, 'Finalizada', '2025-12-04 12:44:29'),
(61, 20, 211, 217, 'Grupos', 6, NULL, 2, 3, 217, NULL, 'Finalizada', '2025-12-04 12:44:29'),
(62, 20, 212, 218, 'Grupos', 6, NULL, 6, 4, 212, NULL, 'Finalizada', '2025-12-04 12:44:29'),
(63, 20, 213, 215, 'Grupos', 6, NULL, 3, 1, 213, NULL, 'Finalizada', '2025-12-04 12:44:29'),
(64, 20, 214, 216, 'Grupos', 6, NULL, 2, 8, 216, NULL, 'Finalizada', '2025-12-04 12:44:29'),
(65, 20, 211, 218, 'Grupos', 7, NULL, 5, 3, 211, NULL, 'Finalizada', '2025-12-04 12:44:29'),
(66, 20, 212, 217, 'Grupos', 7, NULL, 5, 7, 217, NULL, 'Finalizada', '2025-12-04 12:44:29'),
(67, 20, 213, 216, 'Grupos', 7, NULL, 2, 5, 216, NULL, 'Finalizada', '2025-12-04 12:44:29'),
(68, 20, 214, 215, 'Grupos', 7, NULL, 8, 10, 215, NULL, 'Finalizada', '2025-12-04 12:44:29'),
(93, 28, 219, 220, 'Grupos', 1, 21, 12, 5, 219, NULL, 'Finalizada', '2025-12-04 14:18:50'),
(94, 28, 221, 222, 'Grupos', 1, 21, 1, 6, 222, NULL, 'Finalizada', '2025-12-04 14:18:50'),
(95, 28, 219, 221, 'Grupos', 2, 21, 4, 9, 221, NULL, 'Finalizada', '2025-12-04 14:18:50'),
(96, 28, 220, 222, 'Grupos', 2, 21, 8, 9, 222, NULL, 'Finalizada', '2025-12-04 14:18:50'),
(97, 28, 219, 222, 'Grupos', 3, 21, 10, 12, 222, NULL, 'Finalizada', '2025-12-04 14:18:50'),
(98, 28, 220, 221, 'Grupos', 3, 21, 12, 14, 221, NULL, 'Finalizada', '2025-12-04 14:18:50'),
(99, 28, 223, 224, 'Grupos', 1, 22, 8, 10, 224, NULL, 'Finalizada', '2025-12-04 14:18:50'),
(100, 28, 225, 226, 'Grupos', 1, 22, 2, 5, 226, NULL, 'Finalizada', '2025-12-04 14:18:50'),
(101, 28, 223, 225, 'Grupos', 2, 22, 12, 4, 223, NULL, 'Finalizada', '2025-12-04 14:18:50'),
(102, 28, 224, 226, 'Grupos', 2, 22, 7, 9, 226, NULL, 'Finalizada', '2025-12-04 14:18:50'),
(103, 28, 223, 226, 'Grupos', 3, 22, 12, 15, 226, NULL, 'Finalizada', '2025-12-04 14:18:50'),
(104, 28, 224, 225, 'Grupos', 3, 22, 15, 12, 224, NULL, 'Finalizada', '2025-12-04 14:18:50');

-- --------------------------------------------------------

--
-- Estrutura para tabela `torneio_solicitacoes`
--

CREATE TABLE `torneio_solicitacoes` (
  `id` int(11) NOT NULL,
  `torneio_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `status` enum('Pendente','Aprovada','Rejeitada') DEFAULT 'Pendente',
  `data_solicitacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_resposta` timestamp NULL DEFAULT NULL,
  `respondido_por` int(11) DEFAULT NULL COMMENT 'ID do admin que aprovou/rejeitou'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `torneio_solicitacoes`
--

INSERT INTO `torneio_solicitacoes` (`id`, `torneio_id`, `usuario_id`, `status`, `data_solicitacao`, `data_resposta`, `respondido_por`) VALUES
(1, 31, 37, 'Aprovada', '2025-12-04 18:05:42', '2025-12-04 18:14:26', 21),
(10, 31, 27, 'Aprovada', '2025-12-04 19:06:40', '2025-12-04 19:07:17', 21);

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
(211, 20, 'Time 1', '#007bff', 1, '2025-11-25 21:07:12'),
(212, 20, 'Time 2', '#28a745', 2, '2025-11-25 21:07:12'),
(213, 20, 'Time 3', '#dc3545', 3, '2025-11-25 21:07:12'),
(214, 20, 'Time 4', '#ffc107', 4, '2025-11-25 21:07:12'),
(215, 20, 'Time 5', '#17a2b8', 5, '2025-11-25 21:07:12'),
(216, 20, 'Time 6', '#6f42c1', 6, '2025-11-25 21:07:12'),
(217, 20, 'Time 7', '#e83e8c', 7, '2025-11-25 21:07:12'),
(218, 20, 'Time 8', '#fd7e14', 8, '2025-11-25 21:07:12'),
(219, 28, 'Time 1', '#007bff', 1, '2025-12-04 14:12:03'),
(220, 28, 'Time 2', '#28a745', 2, '2025-12-04 14:12:03'),
(221, 28, 'Time 3', '#dc3545', 3, '2025-12-04 14:12:03'),
(222, 28, 'Time 4', '#ffc107', 4, '2025-12-04 14:12:03'),
(223, 28, 'Time 5', '#17a2b8', 5, '2025-12-04 14:12:03'),
(224, 28, 'Time 6', '#6f42c1', 6, '2025-12-04 14:12:03'),
(225, 28, 'Time 7', '#e83e8c', 7, '2025-12-04 14:12:03'),
(226, 28, 'Time 8', '#fd7e14', 8, '2025-12-04 14:12:03'),
(227, 29, 'Time 1', '#007bff', 1, '2025-12-04 17:23:01'),
(228, 29, 'Time 2', '#28a745', 2, '2025-12-04 17:23:01'),
(229, 29, 'Time 3', '#dc3545', 3, '2025-12-04 17:23:01'),
(230, 29, 'Time 4', '#ffc107', 4, '2025-12-04 17:23:01'),
(231, 29, 'Time 5', '#17a2b8', 5, '2025-12-04 17:23:01'),
(232, 29, 'Time 6', '#6f42c1', 6, '2025-12-04 17:23:01'),
(233, 29, 'Time 7', '#e83e8c', 7, '2025-12-04 17:23:01'),
(234, 29, 'Time 8', '#fd7e14', 8, '2025-12-04 17:23:01');

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
(771, 211, 231),
(772, 211, 233),
(774, 212, 237),
(773, 212, 241),
(775, 213, 242),
(776, 213, 244),
(778, 214, 234),
(777, 214, 245),
(779, 215, 232),
(780, 215, 239),
(782, 216, 235),
(781, 216, 238),
(783, 217, 240),
(784, 217, 243),
(785, 218, 230),
(786, 218, 236),
(787, 219, 254),
(788, 219, 261),
(790, 220, 247),
(789, 220, 258),
(791, 221, 250),
(792, 221, 260),
(793, 222, 248),
(794, 222, 249),
(796, 223, 246),
(795, 223, 251),
(798, 224, 252),
(797, 224, 256),
(799, 225, 253),
(800, 225, 255),
(801, 226, 257),
(802, 226, 259),
(803, 227, 269),
(804, 227, 277),
(806, 228, 265),
(805, 228, 272),
(807, 229, 266),
(808, 229, 271),
(810, 230, 264),
(809, 230, 267),
(812, 231, 270),
(811, 231, 275),
(813, 232, 273),
(814, 232, 276),
(816, 233, 268),
(815, 233, 274),
(817, 234, 262),
(818, 234, 263);

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
(21, 'Eduardo Gaier', 'eduardo', NULL, '', 'eduardo@gmail.com', '$2y$10$1tJci0JBDXIJp8t6PloT0.pGIFsiYOqvKAeHDpy/MS548jiiJNwpO', 'Intermediário', 'Masculino', '', NULL, 100, 'assets/arquivos/logousers/21.png', '2025-10-29 21:22:37', 1, 0, NULL, NULL, NULL, 0),
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
(40, 'Lucas cassol', 'lucas', NULL, '55991773435', 'lucas@gmail.com', '$2y$10$LSTBXkdv4B0LwhwWZQpeYOoL5rcQM3bdcrcNM4VRDsG9Sy6a.kIvW', 'Intermediário', 'Masculino', '1', NULL, 100, NULL, '2025-11-14 22:50:31', 1, 0, NULL, NULL, NULL, 0);

--
-- Índices para tabelas despejadas
--

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
  ADD KEY `posicao` (`posicao`),
  ADD KEY `idx_pontos_total_vitorias` (`pontos_total`,`vitorias`,`average`);

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
-- Índices de tabela `torneio_participantes`
--
ALTER TABLE `torneio_participantes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_participant` (`torneio_id`,`usuario_id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `idx_torneio_ordem` (`torneio_id`,`ordem`);

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
  ADD KEY `idx_grupo_id` (`grupo_id`),
  ADD KEY `idx_torneio_rodada` (`torneio_id`,`rodada`),
  ADD KEY `idx_torneio_grupo` (`torneio_id`,`grupo_id`);

--
-- Índices de tabela `torneio_solicitacoes`
--
ALTER TABLE `torneio_solicitacoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_torneio_usuario` (`torneio_id`,`usuario_id`),
  ADD KEY `torneio_id` (`torneio_id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `status` (`status`),
  ADD KEY `fk_torneio_solicitacoes_respondido` (`respondido_por`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT de tabela `logos_grupos`
--
ALTER TABLE `logos_grupos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de tabela `loja_produtos`
--
ALTER TABLE `loja_produtos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `notificacoes`
--
ALTER TABLE `notificacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT de tabela `torneio_chaves_times`
--
ALTER TABLE `torneio_chaves_times`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `torneio_classificacao`
--
ALTER TABLE `torneio_classificacao`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

--
-- AUTO_INCREMENT de tabela `torneio_grupos`
--
ALTER TABLE `torneio_grupos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT de tabela `torneio_grupo_times`
--
ALTER TABLE `torneio_grupo_times`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=153;

--
-- AUTO_INCREMENT de tabela `torneio_participantes`
--
ALTER TABLE `torneio_participantes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=321;

--
-- AUTO_INCREMENT de tabela `torneio_partidas`
--
ALTER TABLE `torneio_partidas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=115;

--
-- AUTO_INCREMENT de tabela `torneio_solicitacoes`
--
ALTER TABLE `torneio_solicitacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de tabela `torneio_times`
--
ALTER TABLE `torneio_times`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=245;

--
-- AUTO_INCREMENT de tabela `torneio_time_integrantes`
--
ALTER TABLE `torneio_time_integrantes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=839;

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
-- Restrições para tabelas `torneio_solicitacoes`
--
ALTER TABLE `torneio_solicitacoes`
  ADD CONSTRAINT `fk_torneio_solicitacoes_respondido` FOREIGN KEY (`respondido_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_torneio_solicitacoes_torneio` FOREIGN KEY (`torneio_id`) REFERENCES `torneios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_torneio_solicitacoes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

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
