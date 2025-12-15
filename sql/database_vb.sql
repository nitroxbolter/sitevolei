-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 15/12/2025 às 15:40
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
-- Estrutura para tabela `grupo_jogos`
--

CREATE TABLE `grupo_jogos` (
  `id` int(11) NOT NULL,
  `grupo_id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL,
  `data_jogo` datetime NOT NULL,
  `local` varchar(200) DEFAULT NULL,
  `modalidade` enum('Dupla','Trio','Quarteto','Quinteto') DEFAULT NULL,
  `quantidade_times` int(11) DEFAULT NULL,
  `integrantes_por_time` int(11) DEFAULT NULL,
  `lista_aberta` tinyint(1) DEFAULT 1 COMMENT '1 = lista aberta, 0 = lista fechada',
  `status` enum('Criado','Lista Aberta','Lista Fechada','Times Criados','Em Andamento','Finalizado','Arquivado') DEFAULT 'Criado',
  `criado_por` int(11) NOT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `grupo_jogos`
--

INSERT INTO `grupo_jogos` (`id`, `grupo_id`, `nome`, `descricao`, `data_jogo`, `local`, `modalidade`, `quantidade_times`, `integrantes_por_time`, `lista_aberta`, `status`, `criado_por`, `data_criacao`) VALUES
(4, 15, 'tetsad', 'asdasd', '2025-12-05 17:30:00', '8000 Sports', 'Quarteto', 3, 4, 1, 'Times Criados', 21, '2025-12-05 20:30:03');

-- --------------------------------------------------------

--
-- Estrutura para tabela `grupo_jogo_classificacao`
--

CREATE TABLE `grupo_jogo_classificacao` (
  `id` int(11) NOT NULL,
  `jogo_id` int(11) NOT NULL,
  `time_id` int(11) NOT NULL,
  `vitorias` int(11) DEFAULT 0,
  `derrotas` int(11) DEFAULT 0,
  `pontos_pro` int(11) DEFAULT 0,
  `pontos_contra` int(11) DEFAULT 0,
  `saldo_pontos` int(11) DEFAULT 0,
  `average` decimal(10,2) DEFAULT 0.00,
  `pontos_total` int(11) DEFAULT 0,
  `posicao` int(11) DEFAULT NULL,
  `data_atualizacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `grupo_jogo_participantes`
--

CREATE TABLE `grupo_jogo_participantes` (
  `id` int(11) NOT NULL,
  `jogo_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `data_inscricao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `grupo_jogo_participantes`
--

INSERT INTO `grupo_jogo_participantes` (`id`, `jogo_id`, `usuario_id`, `data_inscricao`) VALUES
(2, 4, 36, '2025-12-05 20:35:23'),
(3, 4, 29, '2025-12-05 20:35:26'),
(5, 4, 22, '2025-12-05 21:01:49'),
(6, 4, 21, '2025-12-05 21:01:51'),
(7, 4, 28, '2025-12-05 21:01:54'),
(8, 4, 2, '2025-12-05 21:01:59'),
(10, 4, 23, '2025-12-05 21:02:04'),
(11, 4, 25, '2025-12-05 21:02:05'),
(12, 4, 24, '2025-12-05 21:02:08'),
(14, 4, 35, '2025-12-06 11:53:29'),
(15, 4, 27, '2025-12-06 12:13:11'),
(16, 4, 34, '2025-12-06 12:13:13');

-- --------------------------------------------------------

--
-- Estrutura para tabela `grupo_jogo_partidas`
--

CREATE TABLE `grupo_jogo_partidas` (
  `id` int(11) NOT NULL,
  `jogo_id` int(11) NOT NULL,
  `time1_id` int(11) NOT NULL,
  `time2_id` int(11) NOT NULL,
  `pontos_time1` int(11) DEFAULT 0,
  `pontos_time2` int(11) DEFAULT 0,
  `vencedor_id` int(11) DEFAULT NULL,
  `rodada` int(11) DEFAULT 1,
  `status` enum('Agendada','Em Andamento','Finalizada') DEFAULT 'Agendada',
  `data_partida` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `grupo_jogo_times`
--

CREATE TABLE `grupo_jogo_times` (
  `id` int(11) NOT NULL,
  `jogo_id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL,
  `cor` varchar(7) DEFAULT '#007bff',
  `ordem` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `grupo_jogo_times`
--

INSERT INTO `grupo_jogo_times` (`id`, `jogo_id`, `nome`, `cor`, `ordem`) VALUES
(28, 4, 'Time 1', '#007bff', 1),
(29, 4, 'Time 2', '#28a745', 2),
(30, 4, 'Time 3', '#dc3545', 3);

-- --------------------------------------------------------

--
-- Estrutura para tabela `grupo_jogo_time_integrantes`
--

CREATE TABLE `grupo_jogo_time_integrantes` (
  `id` int(11) NOT NULL,
  `time_id` int(11) NOT NULL,
  `participante_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `grupo_jogo_time_integrantes`
--

INSERT INTO `grupo_jogo_time_integrantes` (`id`, `time_id`, `participante_id`) VALUES
(58, 3, 5),
(61, 3, 6),
(60, 3, 11);

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
(36, 15, 39, '2025-11-14 20:00:11', 1),
(37, 14, 21, '2025-12-07 02:10:22', 0);

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
(50, 21, 'Você foi aceito no jogo', 'Sua solicitação para o jogo #26 foi aprovada pelo criador.', 1, '2025-12-05 02:59:25'),
(51, 37, 'Solicitação de participação aprovada', 'Sua solicitação de participação no torneio \"Verao 2026\" foi aprovada!', 1, '2025-12-05 17:08:32'),
(52, 37, 'Solicitação de participação aprovada', 'Sua solicitação de participação no torneio \"Verao 2026\" foi aprovada!', 1, '2025-12-05 17:11:07'),
(53, 37, 'Solicitação de participação aprovada', 'Sua solicitação de participação no torneio \"Verao 2026\" foi aprovada!', 1, '2025-12-05 17:16:20'),
(54, 37, 'Você foi removido do torneio', 'Você foi removido do torneio \"Verao 2026\" pelo administrador.', 1, '2025-12-05 17:16:27'),
(55, 37, 'Solicitação de participação aprovada', 'Sua solicitação de participação no torneio \"Verao 2026\" foi aprovada!', 0, '2025-12-05 17:17:34'),
(56, 28, 'Você foi removido do torneio', 'Você foi removido do torneio \"Unidos pelo Volei\" pelo administrador.', 0, '2025-12-07 01:38:13'),
(57, 24, 'Nova solicitação no grupo', 'Você recebeu uma solicitação de entrada no grupo #14.', 0, '2025-12-07 02:10:22');

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
-- Estrutura para tabela `partidas_2fase_classificacao`
--

CREATE TABLE `partidas_2fase_classificacao` (
  `id` int(11) NOT NULL,
  `torneio_id` int(11) NOT NULL COMMENT 'ID do torneio',
  `time_id` int(11) NOT NULL COMMENT 'ID do time',
  `grupo_id` int(11) NOT NULL COMMENT 'ID do grupo da 2ª fase (Ouro A, Ouro B, Prata A, Prata B, Bronze A, Bronze B)',
  `vitorias` int(11) DEFAULT 0 COMMENT 'Número de vitórias',
  `derrotas` int(11) DEFAULT 0 COMMENT 'Número de derrotas',
  `empates` int(11) DEFAULT 0 COMMENT 'Número de empates',
  `pontos_pro` int(11) DEFAULT 0 COMMENT 'Pontos marcados',
  `pontos_contra` int(11) DEFAULT 0 COMMENT 'Pontos sofridos',
  `saldo_pontos` int(11) DEFAULT 0 COMMENT 'Saldo de pontos (pontos_pro - pontos_contra)',
  `average` decimal(10,2) DEFAULT 0.00 COMMENT 'Average = pontos_pro / pontos_contra (quando pontos_contra > 0)',
  `pontos_total` int(11) DEFAULT 0 COMMENT 'Pontos totais (3 vitórias, 1 empate, 0 derrota)',
  `posicao` int(11) DEFAULT NULL COMMENT 'Posição na classificação do grupo',
  `data_atualizacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Data da última atualização'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Classificação dos grupos da 2ª fase (Ouro A, Ouro B, Prata A, Prata B, Bronze A, Bronze B)';

--
-- Despejando dados para a tabela `partidas_2fase_classificacao`
--

INSERT INTO `partidas_2fase_classificacao` (`id`, `torneio_id`, `time_id`, `grupo_id`, `vitorias`, `derrotas`, `empates`, `pontos_pro`, `pontos_contra`, `saldo_pontos`, `average`, `pontos_total`, `posicao`, `data_atualizacao`) VALUES
(523, 33, 639, 1937, 2, 1, 0, 51, 39, 12, 1.31, 6, 1, '2025-12-14 17:36:46'),
(524, 33, 647, 1937, 1, 2, 0, 46, 50, -4, 0.92, 3, 3, '2025-12-14 17:36:29'),
(525, 33, 655, 1937, 1, 2, 0, 40, 50, -10, 0.80, 3, 4, '2025-12-14 17:36:29'),
(526, 33, 656, 1937, 2, 1, 0, 50, 48, 2, 1.04, 6, 2, '2025-12-14 17:36:46'),
(527, 33, 640, 1938, 3, 0, 0, 54, 28, 26, 1.93, 9, 1, '2025-12-14 17:34:02'),
(528, 33, 643, 1938, 1, 2, 0, 43, 53, -10, 0.81, 3, 3, '2025-12-14 17:34:16'),
(529, 33, 654, 1938, 0, 3, 0, 28, 55, -27, 0.51, 0, 4, '2025-12-14 17:34:16'),
(530, 33, 663, 1938, 2, 1, 0, 46, 35, 11, 1.31, 6, 2, '2025-12-14 17:33:40'),
(531, 33, 636, 1939, 0, 0, 0, 0, 0, 0, 0.00, 0, 1, '2025-12-14 17:17:32'),
(532, 33, 653, 1939, 0, 0, 0, 0, 0, 0, 0.00, 0, 2, '2025-12-14 17:17:32'),
(533, 33, 661, 1939, 0, 0, 0, 0, 0, 0, 0.00, 0, 3, '2025-12-14 17:17:32'),
(534, 33, 664, 1939, 0, 0, 0, 0, 0, 0, 0.00, 0, 4, '2025-12-14 17:17:32'),
(535, 33, 635, 1940, 0, 0, 0, 0, 0, 0, 0.00, 0, 1, '2025-12-14 17:17:32'),
(536, 33, 641, 1940, 0, 0, 0, 0, 0, 0, 0.00, 0, 2, '2025-12-14 17:17:32'),
(537, 33, 646, 1940, 0, 0, 0, 0, 0, 0, 0.00, 0, 3, '2025-12-14 17:17:32'),
(538, 33, 657, 1940, 0, 0, 0, 0, 0, 0, 0.00, 0, 4, '2025-12-14 17:17:32'),
(539, 33, 638, 1941, 2, 1, 0, 42, 45, -3, 0.93, 6, 3, '2025-12-14 17:24:37'),
(540, 33, 648, 1941, 2, 1, 0, 52, 38, 14, 1.37, 6, 1, '2025-12-14 17:24:37'),
(541, 33, 649, 1941, 2, 1, 0, 49, 51, -2, 0.96, 6, 2, '2025-12-14 17:24:37'),
(542, 33, 659, 1941, 0, 3, 0, 46, 55, -9, 0.84, 0, 4, '2025-12-14 17:24:21'),
(543, 33, 644, 1942, 3, 0, 0, 56, 40, 16, 1.40, 9, 1, '2025-12-14 17:29:47'),
(544, 33, 650, 1942, 1, 2, 0, 53, 46, 7, 1.15, 3, 3, '2025-12-14 17:21:25'),
(545, 33, 652, 1942, 0, 3, 0, 25, 54, -29, 0.46, 0, 4, '2025-12-14 17:29:47'),
(546, 33, 660, 1942, 2, 1, 0, 53, 47, 6, 1.13, 6, 2, '2025-12-14 17:29:47');

-- --------------------------------------------------------

--
-- Estrutura para tabela `partidas_2fase_eliminatorias`
--

CREATE TABLE `partidas_2fase_eliminatorias` (
  `id` int(11) NOT NULL,
  `torneio_id` int(11) NOT NULL COMMENT 'ID do torneio',
  `time1_id` int(11) NOT NULL COMMENT 'ID do time 1',
  `time2_id` int(11) NOT NULL COMMENT 'ID do time 2',
  `serie` enum('Ouro','Ouro A','Ouro B','Prata','Prata A','Prata B','Bronze','Bronze A','Bronze B') NOT NULL COMMENT 'Série da eliminatória (Ouro, Prata, Bronze para semi-finais/finais; Ouro A, Ouro B, etc. para outras fases)',
  `tipo_eliminatoria` enum('Semi-Final','Final','3º Lugar') NOT NULL COMMENT 'Tipo de eliminatória',
  `rodada` int(11) DEFAULT 1 COMMENT 'Número da rodada',
  `quadra` int(11) DEFAULT NULL COMMENT 'Número da quadra',
  `pontos_time1` int(11) DEFAULT 0 COMMENT 'Pontos do time 1',
  `pontos_time2` int(11) DEFAULT 0 COMMENT 'Pontos do time 2',
  `vencedor_id` int(11) DEFAULT NULL COMMENT 'ID do time vencedor',
  `status` enum('Agendada','Em Andamento','Finalizada') DEFAULT 'Agendada' COMMENT 'Status da partida',
  `data_partida` datetime DEFAULT NULL COMMENT 'Data e hora da partida',
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Data de criação do registro'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Jogos eliminatórios da 2ª fase (semi-finais, finais, 3º lugar)';

--
-- Despejando dados para a tabela `partidas_2fase_eliminatorias`
--

INSERT INTO `partidas_2fase_eliminatorias` (`id`, `torneio_id`, `time1_id`, `time2_id`, `serie`, `tipo_eliminatoria`, `rodada`, `quadra`, `pontos_time1`, `pontos_time2`, `vencedor_id`, `status`, `data_partida`, `data_criacao`) VALUES
(84, 33, 648, 644, 'Bronze', 'Semi-Final', 1, 1, 0, 0, NULL, 'Agendada', NULL, '2025-12-14 17:24:38'),
(85, 33, 649, 660, 'Bronze', 'Semi-Final', 1, 1, 0, 0, NULL, 'Agendada', NULL, '2025-12-14 17:24:38'),
(86, 33, 639, 663, 'Ouro', 'Semi-Final', 1, 1, 10, 18, 663, 'Finalizada', NULL, '2025-12-14 17:36:46'),
(87, 33, 656, 640, 'Ouro', 'Semi-Final', 1, 1, 9, 18, 640, 'Finalizada', NULL, '2025-12-14 17:36:46'),
(88, 33, 663, 640, 'Ouro', 'Final', 1, 1, 0, 0, NULL, 'Agendada', NULL, '2025-12-14 17:40:14');

-- --------------------------------------------------------

--
-- Estrutura para tabela `partidas_2fase_torneio`
--

CREATE TABLE `partidas_2fase_torneio` (
  `id` int(11) NOT NULL,
  `torneio_id` int(11) NOT NULL COMMENT 'ID do torneio',
  `time1_id` int(11) NOT NULL COMMENT 'ID do time 1',
  `time2_id` int(11) NOT NULL COMMENT 'ID do time 2',
  `grupo_id` int(11) NOT NULL COMMENT 'ID do grupo da 2ª fase (Ouro A, Ouro B, Prata A, Prata B, Bronze A, Bronze B)',
  `rodada` int(11) DEFAULT 1 COMMENT 'Número da rodada',
  `quadra` int(11) DEFAULT NULL COMMENT 'Número da quadra',
  `pontos_time1` int(11) DEFAULT 0 COMMENT 'Pontos do time 1',
  `pontos_time2` int(11) DEFAULT 0 COMMENT 'Pontos do time 2',
  `vencedor_id` int(11) DEFAULT NULL COMMENT 'ID do time vencedor',
  `status` enum('Agendada','Em Andamento','Finalizada') DEFAULT 'Agendada' COMMENT 'Status da partida',
  `data_partida` datetime DEFAULT NULL COMMENT 'Data e hora da partida',
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Data de criação do registro'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Jogos todos contra todos da 2ª fase do torneio';

--
-- Despejando dados para a tabela `partidas_2fase_torneio`
--

INSERT INTO `partidas_2fase_torneio` (`id`, `torneio_id`, `time1_id`, `time2_id`, `grupo_id`, `rodada`, `quadra`, `pontos_time1`, `pontos_time2`, `vencedor_id`, `status`, `data_partida`, `data_criacao`) VALUES
(901, 33, 639, 647, 1937, 1, 1, 18, 13, 639, 'Finalizada', NULL, '2025-12-14 17:17:32'),
(902, 33, 655, 656, 1937, 1, 1, 18, 14, 655, 'Finalizada', NULL, '2025-12-14 17:17:32'),
(903, 33, 639, 655, 1937, 2, 1, 18, 8, 639, 'Finalizada', NULL, '2025-12-14 17:17:32'),
(904, 33, 647, 656, 1937, 2, 1, 15, 18, 656, 'Finalizada', NULL, '2025-12-14 17:17:32'),
(905, 33, 647, 655, 1937, 3, 1, 18, 14, 647, 'Finalizada', NULL, '2025-12-14 17:17:32'),
(906, 33, 639, 656, 1937, 3, 1, 15, 18, 656, 'Finalizada', NULL, '2025-12-14 17:17:32'),
(907, 33, 643, 654, 1938, 1, 2, 19, 17, 643, 'Finalizada', NULL, '2025-12-14 17:17:32'),
(908, 33, 640, 663, 1938, 1, 2, 18, 10, 640, 'Finalizada', NULL, '2025-12-14 17:17:32'),
(909, 33, 640, 654, 1938, 2, 2, 18, 5, 640, 'Finalizada', NULL, '2025-12-14 17:17:32'),
(910, 33, 643, 663, 1938, 2, 2, 11, 18, 663, 'Finalizada', NULL, '2025-12-14 17:17:32'),
(911, 33, 654, 663, 1938, 3, 2, 6, 18, 663, 'Finalizada', NULL, '2025-12-14 17:17:32'),
(912, 33, 640, 643, 1938, 3, 2, 18, 13, 640, 'Finalizada', NULL, '2025-12-14 17:17:32'),
(913, 33, 661, 664, 1939, 1, 3, 0, 0, NULL, 'Agendada', NULL, '2025-12-14 17:17:32'),
(914, 33, 636, 653, 1939, 1, 3, 0, 0, NULL, 'Agendada', NULL, '2025-12-14 17:17:32'),
(915, 33, 653, 661, 1939, 2, 3, 0, 0, NULL, 'Agendada', NULL, '2025-12-14 17:17:32'),
(916, 33, 636, 664, 1939, 2, 3, 0, 0, NULL, 'Agendada', NULL, '2025-12-14 17:17:32'),
(917, 33, 636, 661, 1939, 3, 3, 0, 0, NULL, 'Agendada', NULL, '2025-12-14 17:17:32'),
(918, 33, 653, 664, 1939, 3, 3, 0, 0, NULL, 'Agendada', NULL, '2025-12-14 17:17:32'),
(919, 33, 635, 641, 1940, 1, 4, 0, 0, NULL, 'Agendada', NULL, '2025-12-14 17:17:32'),
(920, 33, 646, 657, 1940, 1, 4, 0, 0, NULL, 'Agendada', NULL, '2025-12-14 17:17:32'),
(921, 33, 641, 646, 1940, 2, 4, 0, 0, NULL, 'Agendada', NULL, '2025-12-14 17:17:32'),
(922, 33, 635, 657, 1940, 2, 4, 0, 0, NULL, 'Agendada', NULL, '2025-12-14 17:17:32'),
(923, 33, 635, 646, 1940, 3, 4, 0, 0, NULL, 'Agendada', NULL, '2025-12-14 17:17:32'),
(924, 33, 641, 657, 1940, 3, 4, 0, 0, NULL, 'Agendada', NULL, '2025-12-14 17:17:32'),
(925, 33, 638, 649, 1941, 1, 5, 18, 12, 638, 'Finalizada', NULL, '2025-12-14 17:17:32'),
(926, 33, 648, 659, 1941, 1, 5, 18, 14, 648, 'Finalizada', NULL, '2025-12-14 17:17:32'),
(927, 33, 638, 648, 1941, 2, 5, 6, 18, 648, 'Finalizada', NULL, '2025-12-14 17:17:32'),
(928, 33, 649, 659, 1941, 2, 5, 19, 17, 649, 'Finalizada', NULL, '2025-12-14 17:17:32'),
(929, 33, 638, 659, 1941, 3, 5, 18, 15, 638, 'Finalizada', NULL, '2025-12-14 17:17:32'),
(930, 33, 648, 649, 1941, 3, 5, 16, 18, 649, 'Finalizada', NULL, '2025-12-14 17:17:32'),
(931, 33, 644, 650, 1942, 1, 6, 20, 18, 644, 'Finalizada', NULL, '2025-12-14 17:17:32'),
(932, 33, 652, 660, 1942, 1, 6, 12, 18, 660, 'Finalizada', NULL, '2025-12-14 17:17:32'),
(933, 33, 650, 652, 1942, 2, 6, 18, 7, 650, 'Finalizada', NULL, '2025-12-14 17:17:32'),
(934, 33, 644, 660, 1942, 2, 6, 18, 16, 644, 'Finalizada', NULL, '2025-12-14 17:17:32'),
(935, 33, 650, 660, 1942, 3, 6, 17, 19, 660, 'Finalizada', NULL, '2025-12-14 17:17:32'),
(936, 33, 644, 652, 1942, 3, 6, 18, 6, 644, 'Finalizada', NULL, '2025-12-14 17:17:32');

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
  `modalidade` enum('todos_contra_todos','todos_chaves','torneio_pro') DEFAULT NULL,
  `quantidade_grupos` int(11) DEFAULT NULL,
  `quantidade_quadras` int(11) DEFAULT 1,
  `status` enum('Criado','Inscrições Abertas','Em Andamento','Finalizado','Cancelado') DEFAULT 'Criado',
  `criado_por` int(11) NOT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `inscricoes_abertas` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `torneios`
--

INSERT INTO `torneios` (`id`, `nome`, `descricao`, `grupo_id`, `tipo`, `data_inicio`, `data_fim`, `max_participantes`, `quantidade_times`, `integrantes_por_time`, `modalidade`, `quantidade_grupos`, `quantidade_quadras`, `status`, `criado_por`, `data_criacao`, `inscricoes_abertas`) VALUES
(33, 'Diretoria Do Volei', '2º Torneio de volei da diretoria 2025', NULL, 'avulso', '2025-12-31 00:00:00', NULL, 120, 30, 4, 'torneio_pro', 6, 6, 'Criado', 21, '2025-12-13 22:49:24', 0);

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

-- --------------------------------------------------------

--
-- Estrutura para tabela `torneio_classificacao`
--

CREATE TABLE `torneio_classificacao` (
  `id` int(11) NOT NULL,
  `torneio_id` int(11) NOT NULL,
  `time_id` int(11) NOT NULL,
  `grupo_id` int(11) DEFAULT NULL,
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

INSERT INTO `torneio_classificacao` (`id`, `torneio_id`, `time_id`, `grupo_id`, `vitorias`, `derrotas`, `empates`, `pontos_pro`, `pontos_contra`, `saldo_pontos`, `average`, `pontos_total`, `posicao`, `data_atualizacao`) VALUES
(15485, 33, 635, 1919, 2, 2, 0, 63, 50, 13, 1.26, 6, 2, '2025-12-14 12:50:33'),
(15486, 33, 636, 1919, 2, 2, 0, 71, 60, 11, 1.18, 6, 3, '2025-12-14 13:23:13'),
(15487, 33, 637, 1919, 1, 3, 0, 33, 67, -34, 0.49, 3, 5, '2025-12-14 13:23:13'),
(15488, 33, 638, 1919, 1, 3, 0, 57, 72, -15, 0.79, 3, 4, '2025-12-14 13:23:35'),
(15489, 33, 639, 1919, 4, 0, 0, 73, 48, 25, 1.52, 12, 1, '2025-12-14 13:23:35'),
(15490, 33, 640, 1920, 4, 0, 0, 72, 39, 33, 1.85, 12, 1, '2025-12-14 13:36:01'),
(15491, 33, 641, 1920, 2, 2, 0, 59, 61, -2, 0.97, 6, 3, '2025-12-14 13:36:30'),
(15492, 33, 642, 1920, 0, 4, 0, 41, 72, -31, 0.57, 0, 5, '2025-12-14 13:36:30'),
(15493, 33, 643, 1920, 3, 1, 0, 63, 57, 6, 1.11, 9, 2, '2025-12-14 13:36:46'),
(15494, 33, 644, 1920, 1, 3, 0, 53, 59, -6, 0.90, 3, 4, '2025-12-14 13:36:46'),
(15495, 33, 645, 1921, 1, 3, 0, 47, 66, -19, 0.71, 3, 5, '2025-12-14 13:27:53'),
(15496, 33, 646, 1921, 2, 2, 0, 69, 48, 21, 1.44, 6, 2, '2025-12-14 13:28:30'),
(15497, 33, 647, 1921, 4, 0, 0, 73, 56, 17, 1.30, 12, 1, '2025-12-14 13:28:30'),
(15498, 33, 648, 1921, 2, 2, 0, 57, 68, -11, 0.84, 6, 3, '2025-12-14 13:27:53'),
(15499, 33, 649, 1921, 1, 3, 0, 54, 62, -8, 0.87, 3, 4, '2025-12-14 13:28:13'),
(15500, 33, 650, 1922, 1, 3, 0, 57, 63, -6, 0.90, 3, 4, '2025-12-14 13:08:00'),
(15501, 33, 651, 1922, 1, 3, 0, 56, 67, -11, 0.84, 3, 5, '2025-12-14 13:08:39'),
(15502, 33, 652, 1922, 1, 3, 0, 59, 65, -6, 0.91, 3, 3, '2025-12-14 13:08:39'),
(15503, 33, 653, 1922, 3, 1, 0, 63, 64, -1, 0.98, 9, 2, '2025-12-14 13:09:27'),
(15504, 33, 654, 1922, 4, 0, 0, 72, 48, 24, 1.50, 12, 1, '2025-12-14 13:09:27'),
(15505, 33, 655, 1923, 3, 1, 0, 66, 47, 19, 1.40, 9, 2, '2025-12-14 13:15:37'),
(15506, 33, 656, 1923, 3, 1, 0, 61, 33, 28, 1.85, 9, 1, '2025-12-14 13:14:59'),
(15507, 33, 657, 1923, 2, 2, 0, 57, 63, -6, 0.90, 6, 3, '2025-12-14 13:16:01'),
(15508, 33, 658, 1923, 1, 3, 0, 47, 70, -23, 0.67, 3, 5, '2025-12-14 13:20:24'),
(15509, 33, 659, 1923, 1, 3, 0, 52, 70, -18, 0.74, 3, 4, '2025-12-14 13:20:24'),
(15510, 33, 660, 1924, 2, 2, 0, 55, 65, -10, 0.85, 6, 4, '2025-12-14 13:30:10'),
(15511, 33, 661, 1924, 2, 2, 0, 69, 54, 15, 1.28, 6, 2, '2025-12-14 13:30:10'),
(15512, 33, 662, 1924, 0, 4, 0, 39, 72, -33, 0.54, 0, 5, '2025-12-14 13:30:10'),
(15513, 33, 663, 1924, 4, 0, 0, 72, 42, 30, 1.71, 12, 1, '2025-12-14 13:30:25'),
(15514, 33, 664, 1924, 2, 2, 0, 61, 63, -2, 0.97, 6, 3, '2025-12-14 13:30:25');

-- --------------------------------------------------------

--
-- Estrutura para tabela `torneio_grupos`
--

CREATE TABLE `torneio_grupos` (
  `id` int(11) NOT NULL,
  `torneio_id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL,
  `ordem` int(11) DEFAULT 1 COMMENT 'Ordem de exibição do grupo',
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Grupos do torneio (A, B, C, etc.)';

--
-- Despejando dados para a tabela `torneio_grupos`
--

INSERT INTO `torneio_grupos` (`id`, `torneio_id`, `nome`, `ordem`, `data_criacao`) VALUES
(1919, 33, 'Grupo A', 1, '2025-12-14 04:00:37'),
(1920, 33, 'Grupo B', 2, '2025-12-14 04:00:37'),
(1921, 33, 'Grupo C', 3, '2025-12-14 04:00:37'),
(1922, 33, 'Grupo D', 4, '2025-12-14 04:00:37'),
(1923, 33, 'Grupo E', 5, '2025-12-14 04:00:37'),
(1924, 33, 'Grupo F', 6, '2025-12-14 04:00:37'),
(1937, 33, '2ª Fase - Ouro A', 100, '2025-12-14 17:16:52'),
(1938, 33, '2ª Fase - Ouro B', 101, '2025-12-14 17:16:52'),
(1939, 33, '2ª Fase - Prata A', 102, '2025-12-14 17:16:52'),
(1940, 33, '2ª Fase - Prata B', 103, '2025-12-14 17:16:52'),
(1941, 33, '2ª Fase - Bronze A', 104, '2025-12-14 17:16:52'),
(1942, 33, '2ª Fase - Bronze B', 105, '2025-12-14 17:16:52'),
(1943, 33, '2ª Fase - Ouro - Classificação', 150, '2025-12-14 17:17:32'),
(1944, 33, '2ª Fase - Ouro - Chaves', 200, '2025-12-14 17:17:32'),
(1945, 33, '2ª Fase - Prata - Classificação', 150, '2025-12-14 17:17:32'),
(1946, 33, '2ª Fase - Prata - Chaves', 200, '2025-12-14 17:17:32'),
(1947, 33, '2ª Fase - Bronze - Classificação', 150, '2025-12-14 17:17:32'),
(1948, 33, '2ª Fase - Bronze - Chaves', 200, '2025-12-14 17:17:32');

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
(6047, 1919, 635, '2025-12-14 04:00:37'),
(6048, 1919, 636, '2025-12-14 04:00:37'),
(6049, 1919, 637, '2025-12-14 04:00:37'),
(6050, 1919, 638, '2025-12-14 04:00:37'),
(6051, 1919, 639, '2025-12-14 04:00:37'),
(6052, 1920, 640, '2025-12-14 04:00:37'),
(6053, 1920, 641, '2025-12-14 04:00:37'),
(6054, 1920, 642, '2025-12-14 04:00:37'),
(6055, 1920, 643, '2025-12-14 04:00:37'),
(6056, 1920, 644, '2025-12-14 04:00:37'),
(6057, 1921, 645, '2025-12-14 04:00:37'),
(6058, 1921, 646, '2025-12-14 04:00:37'),
(6059, 1921, 647, '2025-12-14 04:00:37'),
(6060, 1921, 648, '2025-12-14 04:00:37'),
(6061, 1921, 649, '2025-12-14 04:00:37'),
(6062, 1922, 650, '2025-12-14 04:00:37'),
(6063, 1922, 651, '2025-12-14 04:00:37'),
(6064, 1922, 652, '2025-12-14 04:00:37'),
(6065, 1922, 653, '2025-12-14 04:00:37'),
(6066, 1922, 654, '2025-12-14 04:00:37'),
(6067, 1923, 655, '2025-12-14 04:00:37'),
(6068, 1923, 656, '2025-12-14 04:00:37'),
(6069, 1923, 657, '2025-12-14 04:00:37'),
(6070, 1923, 658, '2025-12-14 04:00:37'),
(6071, 1923, 659, '2025-12-14 04:00:37'),
(6072, 1924, 660, '2025-12-14 04:00:37'),
(6073, 1924, 661, '2025-12-14 04:00:37'),
(6074, 1924, 662, '2025-12-14 04:00:37'),
(6075, 1924, 663, '2025-12-14 04:00:37'),
(6076, 1924, 664, '2025-12-14 04:00:37'),
(6101, 1937, 639, '2025-12-14 17:16:52'),
(6102, 1937, 647, '2025-12-14 17:16:52'),
(6103, 1937, 656, '2025-12-14 17:16:52'),
(6104, 1937, 655, '2025-12-14 17:16:52'),
(6105, 1938, 640, '2025-12-14 17:16:52'),
(6106, 1938, 654, '2025-12-14 17:16:52'),
(6107, 1938, 663, '2025-12-14 17:16:52'),
(6108, 1938, 643, '2025-12-14 17:16:52'),
(6109, 1939, 653, '2025-12-14 17:16:52'),
(6110, 1939, 661, '2025-12-14 17:16:52'),
(6111, 1939, 636, '2025-12-14 17:16:52'),
(6112, 1939, 664, '2025-12-14 17:16:52'),
(6113, 1940, 646, '2025-12-14 17:16:52'),
(6114, 1940, 635, '2025-12-14 17:16:52'),
(6115, 1940, 641, '2025-12-14 17:16:52'),
(6116, 1940, 657, '2025-12-14 17:16:52'),
(6117, 1941, 648, '2025-12-14 17:16:52'),
(6118, 1941, 638, '2025-12-14 17:16:52'),
(6119, 1941, 649, '2025-12-14 17:16:52'),
(6120, 1941, 659, '2025-12-14 17:16:52'),
(6121, 1942, 652, '2025-12-14 17:16:52'),
(6122, 1942, 644, '2025-12-14 17:16:52'),
(6123, 1942, 650, '2025-12-14 17:16:52'),
(6124, 1942, 660, '2025-12-14 17:16:52');

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
(1290, 33, NULL, 'marcos machado', 1, '2025-12-13 22:51:55', NULL),
(1291, 33, NULL, 'rodrigo kunz', 2, '2025-12-13 22:51:55', NULL),
(1292, 33, NULL, 'carla indiele', 3, '2025-12-13 22:51:55', NULL),
(1293, 33, NULL, 'michele flores', 4, '2025-12-13 22:51:55', NULL),
(1294, 33, NULL, 'pâmela claussen', 5, '2025-12-13 22:51:55', NULL),
(1295, 33, NULL, 'camila martins', 6, '2025-12-13 22:51:55', NULL),
(1296, 33, NULL, 'edenílson gomes', 7, '2025-12-13 22:51:55', NULL),
(1297, 33, NULL, 'júlio césar', 8, '2025-12-13 22:51:55', NULL),
(1298, 33, NULL, 'pâmella souza', 9, '2025-12-13 22:51:55', NULL),
(1299, 33, NULL, 'evelyn lucas', 10, '2025-12-13 22:51:55', NULL),
(1300, 33, NULL, 'iuri aguiar', 11, '2025-12-13 22:51:55', NULL),
(1301, 33, NULL, 'gustavo santos', 12, '2025-12-13 22:51:55', NULL),
(1302, 33, NULL, 'samuel bombonatti', 13, '2025-12-13 22:51:55', NULL),
(1303, 33, NULL, 'diego wouters', 14, '2025-12-13 22:51:55', NULL),
(1304, 33, NULL, 'bruna gonçalves', 15, '2025-12-13 22:51:55', NULL),
(1305, 33, NULL, 'nadine sacerdote', 16, '2025-12-13 22:51:55', NULL),
(1306, 33, NULL, 'renan garlet', 17, '2025-12-13 22:51:55', NULL),
(1307, 33, NULL, 'renato', 18, '2025-12-13 22:51:55', NULL),
(1308, 33, NULL, 'andréia', 19, '2025-12-13 22:51:55', NULL),
(1309, 33, NULL, 'josé', 20, '2025-12-13 22:51:55', NULL),
(1310, 33, NULL, 'cristina', 21, '2025-12-13 22:51:55', NULL),
(1311, 33, NULL, 'joão pedro alvez', 22, '2025-12-13 22:51:55', NULL),
(1312, 33, NULL, 'henrique fagan', 23, '2025-12-13 22:51:55', NULL),
(1313, 33, NULL, 'jhennifer porto', 24, '2025-12-13 22:51:55', NULL),
(1314, 33, NULL, 'ana camila', 25, '2025-12-13 22:51:55', NULL),
(1315, 33, NULL, 'igor rodrigues', 26, '2025-12-13 22:51:55', NULL),
(1316, 33, NULL, 'thalis rodrigues', 27, '2025-12-13 22:51:55', NULL),
(1317, 33, NULL, 'alice weber', 28, '2025-12-13 22:51:55', NULL),
(1318, 33, NULL, 'antonela chiochet', 29, '2025-12-13 22:51:55', NULL),
(1319, 33, NULL, 'henrique bones', 30, '2025-12-13 22:51:55', NULL),
(1320, 33, NULL, 'bruno jardim', 31, '2025-12-13 22:51:55', NULL),
(1321, 33, NULL, 'eduarda limberger', 32, '2025-12-13 22:51:55', NULL),
(1322, 33, NULL, 'manu mello', 33, '2025-12-13 22:51:55', NULL),
(1323, 33, NULL, 'clayton bertazzo', 34, '2025-12-13 22:51:55', NULL),
(1324, 33, NULL, 'emelin ferreira', 35, '2025-12-13 22:51:55', NULL),
(1325, 33, NULL, 'marinho santos', 36, '2025-12-13 22:51:55', NULL),
(1326, 33, NULL, 'denize trindade', 37, '2025-12-13 22:51:55', NULL),
(1327, 33, NULL, 'juliano garcia', 38, '2025-12-13 22:51:55', NULL),
(1328, 33, NULL, 'ana julia', 39, '2025-12-13 22:51:55', NULL),
(1329, 33, NULL, 'daniel severo', 40, '2025-12-13 22:51:55', NULL),
(1330, 33, NULL, 'luis miguel', 41, '2025-12-13 22:51:55', NULL),
(1331, 33, NULL, 'rafaela severo', 42, '2025-12-13 22:51:55', NULL),
(1332, 33, NULL, 'dagoberto pereira', 43, '2025-12-13 22:51:55', NULL),
(1333, 33, NULL, 'janaína anschau', 44, '2025-12-13 22:51:55', NULL),
(1334, 33, NULL, 'douglas da rosa', 45, '2025-12-13 22:51:55', NULL),
(1335, 33, NULL, 'júlia aguette', 46, '2025-12-13 22:51:55', NULL),
(1336, 33, NULL, 'luis henrique coden', 47, '2025-12-13 22:51:55', NULL),
(1337, 33, NULL, 'lurdes queiroz', 48, '2025-12-13 22:51:55', NULL),
(1338, 33, NULL, 'samanta corrêa', 49, '2025-12-13 22:51:55', NULL),
(1339, 33, NULL, 'gilberto tormes', 50, '2025-12-13 22:51:55', NULL),
(1340, 33, NULL, 'fábio almeida', 51, '2025-12-13 22:51:55', NULL),
(1341, 33, NULL, 'yuri rodrigues', 52, '2025-12-13 22:51:55', NULL),
(1342, 33, NULL, 'giovana padoin', 53, '2025-12-13 22:51:55', NULL),
(1343, 33, NULL, 'jhenniffer lopes', 54, '2025-12-13 22:51:55', NULL),
(1344, 33, NULL, 'kauê zago', 55, '2025-12-13 22:51:55', NULL),
(1345, 33, NULL, 'gustavo adam', 56, '2025-12-13 22:51:55', NULL),
(1346, 33, NULL, 'thyago lucas', 57, '2025-12-13 22:51:55', NULL),
(1347, 33, NULL, 'adriéli zago', 58, '2025-12-13 22:51:55', NULL),
(1348, 33, NULL, 'izadora hoffmann', 59, '2025-12-13 22:51:55', NULL),
(1349, 33, NULL, 'manuel netto', 60, '2025-12-13 22:51:55', NULL),
(1350, 33, NULL, 'núria brum', 61, '2025-12-13 22:51:55', NULL),
(1351, 33, NULL, 'lidiane marques leal', 62, '2025-12-13 22:51:55', NULL),
(1352, 33, NULL, 'roberto barcelos', 63, '2025-12-13 22:51:55', NULL),
(1353, 33, NULL, 'jamerson caldeira', 64, '2025-12-13 22:51:55', NULL),
(1354, 33, NULL, 'licinara caldeira', 65, '2025-12-13 22:51:55', NULL),
(1355, 33, NULL, 'william werlang', 66, '2025-12-13 22:51:55', NULL),
(1356, 33, NULL, 'fernanda mativi', 67, '2025-12-13 22:51:55', NULL),
(1357, 33, NULL, 'sérgio chimini', 68, '2025-12-13 22:51:55', NULL),
(1358, 33, NULL, 'aline pagel', 69, '2025-12-13 22:51:55', NULL),
(1359, 33, NULL, 'gabriel lopes', 70, '2025-12-13 22:51:55', NULL),
(1360, 33, NULL, 'sabrina chimini', 71, '2025-12-13 22:51:55', NULL),
(1361, 33, NULL, 'felipe moreira', 72, '2025-12-13 22:51:55', NULL),
(1362, 33, NULL, 'thalles de oliveira', 73, '2025-12-13 22:51:55', NULL),
(1363, 33, NULL, 'anelise campos', 74, '2025-12-13 22:51:55', NULL),
(1364, 33, NULL, 'júlia mello', 75, '2025-12-13 22:51:55', NULL),
(1365, 33, NULL, 'daniel fumaça', 76, '2025-12-13 22:51:55', NULL),
(1366, 33, NULL, 'michael andrey', 77, '2025-12-13 22:51:55', NULL),
(1367, 33, NULL, 'gabrieli martini', 78, '2025-12-13 22:51:55', NULL),
(1368, 33, NULL, 'eduarda gripa', 79, '2025-12-13 22:51:55', NULL),
(1369, 33, NULL, 'maria izalanski', 80, '2025-12-13 22:51:55', NULL),
(1370, 33, NULL, 'andressa kleist', 81, '2025-12-13 22:51:55', NULL),
(1371, 33, NULL, 'fernando campos', 82, '2025-12-13 22:51:55', NULL),
(1372, 33, NULL, 'matheus souto', 83, '2025-12-13 22:51:55', NULL),
(1373, 33, NULL, 'bruno ennes', 84, '2025-12-13 22:51:55', NULL),
(1374, 33, NULL, 'jefersson souza', 85, '2025-12-13 22:51:55', NULL),
(1375, 33, NULL, 'aline giacomazzo', 86, '2025-12-13 22:51:55', NULL),
(1376, 33, NULL, 'juliane cheron', 87, '2025-12-13 22:51:55', NULL),
(1377, 33, NULL, 'priscilla morais', 88, '2025-12-13 22:51:55', NULL),
(1378, 33, NULL, 'gabriel fontana', 89, '2025-12-13 22:51:55', NULL),
(1379, 33, NULL, 'anderson dos santos', 90, '2025-12-13 22:51:55', NULL),
(1380, 33, NULL, 'gilce plate', 91, '2025-12-13 22:51:55', NULL),
(1381, 33, NULL, 'rodrigo carvalho', 92, '2025-12-13 22:51:55', NULL),
(1382, 33, NULL, 'alberson soares', 93, '2025-12-13 22:51:55', NULL),
(1383, 33, NULL, 'katiusci lehnhard', 94, '2025-12-13 22:51:55', NULL),
(1384, 33, NULL, 'tiago bremm', 95, '2025-12-13 22:51:55', NULL),
(1385, 33, NULL, 'cris bremm', 96, '2025-12-13 22:51:55', NULL),
(1386, 33, NULL, 'junior gomes', 97, '2025-12-13 22:51:55', NULL),
(1387, 33, NULL, 'laura moro', 98, '2025-12-13 22:51:55', NULL),
(1388, 33, NULL, 'luciellen ribas', 99, '2025-12-13 22:51:55', NULL),
(1389, 33, NULL, 'hércules gonçalves', 100, '2025-12-13 22:51:55', NULL),
(1390, 33, NULL, 'maria julia ventura', 101, '2025-12-13 22:51:55', NULL),
(1391, 33, NULL, 'lavínia jesuíno', 102, '2025-12-13 22:51:55', NULL),
(1392, 33, NULL, 'henry de oliveira', 103, '2025-12-13 22:51:55', NULL),
(1393, 33, NULL, 'eduardo menegaes', 104, '2025-12-13 22:51:55', NULL),
(1394, 33, NULL, 'lary severo', 105, '2025-12-13 22:51:55', NULL),
(1395, 33, NULL, 'adrian kralik', 106, '2025-12-13 22:51:55', NULL),
(1396, 33, NULL, 'vinicios rodrigues', 107, '2025-12-13 22:51:55', NULL),
(1397, 33, NULL, 'renata visentini', 108, '2025-12-13 22:51:55', NULL),
(1398, 33, NULL, 'jonathan wouters', 109, '2025-12-13 22:51:55', NULL),
(1399, 33, NULL, 'joice roque', 110, '2025-12-13 22:51:55', NULL),
(1400, 33, NULL, 'guilherme turna', 111, '2025-12-13 22:51:55', NULL),
(1401, 33, NULL, 'nathalia adam', 112, '2025-12-13 22:51:55', NULL),
(1402, 33, NULL, 'thaís rosa', 113, '2025-12-13 22:51:55', NULL),
(1403, 33, NULL, 'maurício bueno', 114, '2025-12-13 22:51:55', NULL),
(1404, 33, NULL, 'daiana siqueira', 115, '2025-12-13 22:51:55', NULL),
(1405, 33, NULL, 'diego lorensi', 116, '2025-12-13 22:51:55', NULL),
(1406, 33, NULL, 'morgana bevilacqua', 117, '2025-12-13 22:51:55', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `torneio_partidas`
--

CREATE TABLE `torneio_partidas` (
  `id` int(11) NOT NULL,
  `torneio_id` int(11) NOT NULL,
  `time1_id` int(11) NOT NULL,
  `time2_id` int(11) NOT NULL,
  `fase` enum('Grupos','Quartas','Semi','Final','3º Lugar','2ª Fase') DEFAULT 'Grupos',
  `rodada` int(11) DEFAULT 1 COMMENT 'Número da rodada na fase',
  `grupo_id` int(11) DEFAULT NULL COMMENT 'ID do grupo (para modalidade todos_chaves)',
  `quadra` int(11) DEFAULT NULL,
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

INSERT INTO `torneio_partidas` (`id`, `torneio_id`, `time1_id`, `time2_id`, `fase`, `rodada`, `grupo_id`, `quadra`, `pontos_time1`, `pontos_time2`, `vencedor_id`, `data_partida`, `status`, `data_criacao`) VALUES
(6941, 33, 635, 636, 'Grupos', 1, 1919, 1, 15, 18, 636, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6942, 33, 636, 639, 'Grupos', 1, 1919, 1, 17, 19, 639, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6943, 33, 637, 638, 'Grupos', 1, 1919, 1, 18, 13, 637, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6944, 33, 635, 637, 'Grupos', 2, 1919, 1, 18, 2, 635, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6945, 33, 636, 638, 'Grupos', 2, 1919, 1, 18, 20, 638, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6946, 33, 637, 639, 'Grupos', 2, 1919, 1, 7, 18, 639, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6947, 33, 635, 638, 'Grupos', 3, 1919, 1, 18, 12, 635, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6948, 33, 636, 637, 'Grupos', 3, 1919, 1, 18, 6, 636, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6949, 33, 638, 639, 'Grupos', 3, 1919, 1, 12, 18, 639, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6950, 33, 635, 639, 'Grupos', 4, 1919, 1, 12, 18, 639, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6951, 33, 640, 641, 'Grupos', 1, 1920, 2, 18, 9, 640, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6952, 33, 641, 644, 'Grupos', 1, 1920, 2, 18, 12, 641, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6953, 33, 642, 643, 'Grupos', 1, 1920, 2, 11, 18, 643, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6954, 33, 640, 642, 'Grupos', 2, 1920, 2, 18, 12, 640, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6955, 33, 641, 643, 'Grupos', 2, 1920, 2, 14, 18, 643, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6956, 33, 642, 644, 'Grupos', 2, 1920, 2, 5, 18, 644, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6957, 33, 640, 643, 'Grupos', 3, 1920, 2, 18, 9, 640, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6958, 33, 641, 642, 'Grupos', 3, 1920, 2, 18, 13, 641, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6959, 33, 643, 644, 'Grupos', 3, 1920, 2, 18, 14, 643, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6960, 33, 640, 644, 'Grupos', 4, 1920, 2, 18, 9, 640, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6961, 33, 645, 646, 'Grupos', 1, 1921, 3, 0, 18, 646, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6962, 33, 646, 649, 'Grupos', 1, 1921, 3, 18, 11, 646, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6963, 33, 647, 648, 'Grupos', 1, 1921, 3, 18, 13, 647, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6964, 33, 645, 647, 'Grupos', 2, 1921, 3, 13, 18, 647, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6965, 33, 646, 648, 'Grupos', 2, 1921, 3, 16, 18, 648, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6966, 33, 647, 649, 'Grupos', 2, 1921, 3, 18, 13, 647, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6967, 33, 645, 648, 'Grupos', 3, 1921, 3, 16, 18, 648, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6968, 33, 646, 647, 'Grupos', 3, 1921, 3, 17, 19, 647, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6969, 33, 648, 649, 'Grupos', 3, 1921, 3, 8, 18, 649, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6970, 33, 645, 649, 'Grupos', 4, 1921, 3, 18, 12, 645, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6971, 33, 650, 651, 'Grupos', 1, 1922, 4, 13, 18, 651, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6972, 33, 651, 654, 'Grupos', 1, 1922, 4, 12, 18, 654, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6973, 33, 652, 653, 'Grupos', 1, 1922, 4, 16, 18, 653, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6974, 33, 650, 652, 'Grupos', 2, 1922, 4, 18, 9, 650, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6975, 33, 651, 653, 'Grupos', 2, 1922, 4, 15, 18, 653, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6976, 33, 652, 654, 'Grupos', 2, 1922, 4, 16, 18, 654, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6977, 33, 650, 653, 'Grupos', 3, 1922, 4, 15, 18, 653, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6978, 33, 651, 652, 'Grupos', 3, 1922, 4, 11, 18, 652, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6979, 33, 653, 654, 'Grupos', 3, 1922, 4, 9, 18, 654, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6980, 33, 650, 654, 'Grupos', 4, 1922, 4, 11, 18, 654, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6981, 33, 655, 656, 'Grupos', 1, 1923, 5, 18, 7, 655, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6982, 33, 656, 659, 'Grupos', 1, 1923, 5, 18, 8, 656, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6983, 33, 657, 658, 'Grupos', 1, 1923, 5, 18, 15, 657, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6984, 33, 655, 657, 'Grupos', 2, 1923, 5, 12, 18, 657, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6985, 33, 656, 658, 'Grupos', 2, 1923, 5, 18, 2, 656, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6986, 33, 657, 659, 'Grupos', 2, 1923, 5, 16, 18, 659, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6987, 33, 655, 658, 'Grupos', 3, 1923, 5, 18, 12, 655, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6988, 33, 656, 657, 'Grupos', 3, 1923, 5, 18, 5, 656, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6989, 33, 658, 659, 'Grupos', 3, 1923, 5, 18, 16, 658, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6990, 33, 655, 659, 'Grupos', 4, 1923, 5, 18, 10, 655, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6991, 33, 660, 661, 'Grupos', 1, 1924, 6, 12, 18, 661, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6992, 33, 661, 664, 'Grupos', 1, 1924, 6, 17, 19, 664, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6993, 33, 662, 663, 'Grupos', 1, 1924, 6, 10, 18, 663, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6994, 33, 660, 662, 'Grupos', 2, 1924, 6, 18, 14, 660, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6995, 33, 661, 663, 'Grupos', 2, 1924, 6, 16, 18, 663, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6996, 33, 662, 664, 'Grupos', 2, 1924, 6, 10, 18, 664, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6997, 33, 660, 663, 'Grupos', 3, 1924, 6, 7, 18, 663, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6998, 33, 661, 662, 'Grupos', 3, 1924, 6, 18, 5, 661, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(6999, 33, 663, 664, 'Grupos', 3, 1924, 6, 18, 9, 663, NULL, 'Finalizada', '2025-12-14 04:00:42'),
(7000, 33, 660, 664, 'Grupos', 4, 1924, 6, 18, 15, 660, NULL, 'Finalizada', '2025-12-14 04:00:42');

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
(635, 33, 'Farpas', '#007bff', 1, '2025-12-13 22:49:47'),
(636, 33, 'UPG', '#28a745', 2, '2025-12-13 22:49:47'),
(637, 33, 'F5 doVolei', '#dc3545', 3, '2025-12-13 22:49:47'),
(638, 33, 'AVM Volei', '#ffc107', 4, '2025-12-13 22:49:47'),
(639, 33, 'Shadow Blitz', '#17a2b8', 5, '2025-12-13 22:49:47'),
(640, 33, 'Saca No pior', '#6f42c1', 6, '2025-12-13 22:49:47'),
(641, 33, 'Rangers Supreme', '#e83e8c', 7, '2025-12-13 22:49:47'),
(642, 33, 'Danger', '#fd7e14', 8, '2025-12-13 22:49:47'),
(643, 33, 'Quarteto Fantastico', '#20c997', 9, '2025-12-13 22:49:47'),
(644, 33, 'AVM Amigos', '#6610f2', 10, '2025-12-13 22:49:47'),
(645, 33, '4 Tons de Volei', '#343a40', 11, '2025-12-13 22:49:47'),
(646, 33, 'Um time Duvidoso', '#6c757d', 12, '2025-12-13 22:49:47'),
(647, 33, 'Santo Volei B', '#007bff', 13, '2025-12-13 22:49:47'),
(648, 33, 'Bar Sem Lona', '#28a745', 14, '2025-12-13 22:49:47'),
(649, 33, 'Diretoria', '#dc3545', 15, '2025-12-13 22:49:47'),
(650, 33, 'Aleatorio Volei', '#ffc107', 16, '2025-12-13 22:49:47'),
(651, 33, 'Os 4 Elementos', '#17a2b8', 17, '2025-12-13 22:49:47'),
(652, 33, 'Prime 4', '#6f42c1', 18, '2025-12-13 22:49:47'),
(653, 33, 'AAAFAS', '#e83e8c', 19, '2025-12-13 22:49:47'),
(654, 33, 'Trupe', '#fd7e14', 20, '2025-12-13 22:49:47'),
(655, 33, 'Vai que Da', '#20c997', 21, '2025-12-13 22:49:47'),
(656, 33, 'Vai que da Diretoria', '#6610f2', 22, '2025-12-13 22:49:47'),
(657, 33, 'Marte Volei', '#343a40', 23, '2025-12-13 22:49:47'),
(658, 33, 'NVS', '#6c757d', 24, '2025-12-13 22:49:47'),
(659, 33, 'Os Pertubados', '#007bff', 25, '2025-12-13 22:49:47'),
(660, 33, 'Dinos 1', '#28a745', 26, '2025-12-13 22:49:47'),
(661, 33, 'Santo Volei A', '#dc3545', 27, '2025-12-13 22:49:47'),
(662, 33, 'Dinos 2', '#ffc107', 28, '2025-12-13 22:49:47'),
(663, 33, 'Os Cansados', '#17a2b8', 29, '2025-12-13 22:49:47'),
(664, 33, 'Rangers', '#6f42c1', 30, '2025-12-13 22:49:47');

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
(3421, 635, 1344),
(3422, 635, 1345),
(3423, 635, 1346),
(3424, 635, 1347),
(3425, 636, 1357),
(3426, 636, 1358),
(3427, 636, 1359),
(3428, 636, 1360),
(3429, 637, 1377),
(3430, 637, 1378),
(3431, 637, 1379),
(3432, 637, 1380),
(3433, 638, 1328),
(3434, 638, 1329),
(3435, 638, 1330),
(3436, 638, 1331),
(3437, 640, 1361),
(3438, 640, 1362),
(3439, 640, 1363),
(3440, 640, 1364),
(3441, 641, 1332),
(3442, 641, 1333),
(3443, 641, 1334),
(3444, 641, 1335),
(3445, 642, 1373),
(3446, 642, 1374),
(3447, 642, 1375),
(3448, 642, 1376),
(3449, 643, 1340),
(3450, 643, 1341),
(3451, 643, 1342),
(3452, 643, 1343),
(3453, 644, 1394),
(3454, 644, 1395),
(3455, 644, 1396),
(3456, 644, 1397),
(3457, 645, 1390),
(3458, 645, 1391),
(3459, 645, 1392),
(3460, 645, 1393),
(3461, 646, 1402),
(3462, 647, 1311),
(3463, 647, 1312),
(3464, 647, 1313),
(3465, 647, 1314),
(3466, 648, 1353),
(3467, 648, 1354),
(3468, 648, 1355),
(3469, 648, 1356),
(3470, 649, 1349),
(3471, 649, 1350),
(3472, 649, 1351),
(3473, 649, 1352),
(3474, 651, 1403),
(3475, 651, 1404),
(3476, 651, 1405),
(3477, 651, 1406),
(3478, 652, 1386),
(3479, 652, 1387),
(3480, 652, 1388),
(3481, 652, 1389),
(3482, 653, 1298),
(3483, 653, 1299),
(3484, 653, 1300),
(3485, 653, 1301),
(3486, 654, 1320),
(3487, 654, 1321),
(3488, 654, 1322),
(3489, 654, 1323),
(3490, 655, 1303),
(3491, 655, 1304),
(3492, 655, 1305),
(3493, 655, 1306),
(3494, 656, 1294),
(3495, 656, 1295),
(3496, 656, 1296),
(3497, 656, 1297),
(3498, 657, 1324),
(3499, 657, 1325),
(3500, 657, 1326),
(3501, 657, 1327),
(3502, 658, 1336),
(3503, 658, 1337),
(3504, 658, 1338),
(3505, 658, 1339),
(3506, 659, 1290),
(3507, 659, 1291),
(3508, 659, 1292),
(3509, 659, 1293),
(3510, 660, 1365),
(3511, 660, 1366),
(3512, 660, 1367),
(3513, 660, 1368),
(3514, 661, 1307),
(3515, 661, 1308),
(3516, 661, 1309),
(3517, 661, 1310),
(3518, 662, 1369),
(3519, 662, 1370),
(3520, 662, 1371),
(3521, 662, 1372),
(3522, 663, 1398),
(3523, 663, 1399),
(3524, 663, 1400),
(3525, 663, 1401),
(3526, 664, 1382),
(3527, 664, 1383),
(3528, 664, 1384),
(3529, 664, 1385);

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
-- Índices de tabela `grupo_jogos`
--
ALTER TABLE `grupo_jogos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `grupo_id` (`grupo_id`),
  ADD KEY `criado_por` (`criado_por`);

--
-- Índices de tabela `grupo_jogo_classificacao`
--
ALTER TABLE `grupo_jogo_classificacao`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `jogo_time` (`jogo_id`,`time_id`),
  ADD KEY `time_id` (`time_id`);

--
-- Índices de tabela `grupo_jogo_participantes`
--
ALTER TABLE `grupo_jogo_participantes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `jogo_usuario` (`jogo_id`,`usuario_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `grupo_jogo_partidas`
--
ALTER TABLE `grupo_jogo_partidas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jogo_id` (`jogo_id`),
  ADD KEY `time1_id` (`time1_id`),
  ADD KEY `time2_id` (`time2_id`);

--
-- Índices de tabela `grupo_jogo_times`
--
ALTER TABLE `grupo_jogo_times`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jogo_id` (`jogo_id`);

--
-- Índices de tabela `grupo_jogo_time_integrantes`
--
ALTER TABLE `grupo_jogo_time_integrantes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `time_participante` (`time_id`,`participante_id`),
  ADD KEY `participante_id` (`participante_id`);

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
-- Índices de tabela `partidas_2fase_classificacao`
--
ALTER TABLE `partidas_2fase_classificacao`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_torneio_time_grupo` (`torneio_id`,`time_id`,`grupo_id`),
  ADD KEY `idx_torneio_id` (`torneio_id`),
  ADD KEY `idx_grupo_id` (`grupo_id`),
  ADD KEY `idx_time_id` (`time_id`),
  ADD KEY `idx_posicao` (`posicao`),
  ADD KEY `idx_torneio_grupo` (`torneio_id`,`grupo_id`);

--
-- Índices de tabela `partidas_2fase_eliminatorias`
--
ALTER TABLE `partidas_2fase_eliminatorias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_torneio_id` (`torneio_id`),
  ADD KEY `idx_serie` (`serie`),
  ADD KEY `idx_tipo_eliminatoria` (`tipo_eliminatoria`),
  ADD KEY `idx_time1_id` (`time1_id`),
  ADD KEY `idx_time2_id` (`time2_id`),
  ADD KEY `idx_vencedor_id` (`vencedor_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_torneio_serie_tipo` (`torneio_id`,`serie`,`tipo_eliminatoria`);

--
-- Índices de tabela `partidas_2fase_torneio`
--
ALTER TABLE `partidas_2fase_torneio`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_torneio_id` (`torneio_id`),
  ADD KEY `idx_grupo_id` (`grupo_id`),
  ADD KEY `idx_time1_id` (`time1_id`),
  ADD KEY `idx_time2_id` (`time2_id`),
  ADD KEY `idx_vencedor_id` (`vencedor_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_torneio_grupo` (`torneio_id`,`grupo_id`);

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
-- AUTO_INCREMENT de tabela `grupo_jogos`
--
ALTER TABLE `grupo_jogos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `grupo_jogo_classificacao`
--
ALTER TABLE `grupo_jogo_classificacao`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `grupo_jogo_participantes`
--
ALTER TABLE `grupo_jogo_participantes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de tabela `grupo_jogo_partidas`
--
ALTER TABLE `grupo_jogo_partidas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `grupo_jogo_times`
--
ALTER TABLE `grupo_jogo_times`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT de tabela `grupo_jogo_time_integrantes`
--
ALTER TABLE `grupo_jogo_time_integrantes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=74;

--
-- AUTO_INCREMENT de tabela `grupo_membros`
--
ALTER TABLE `grupo_membros`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

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
-- AUTO_INCREMENT de tabela `partidas_2fase_classificacao`
--
ALTER TABLE `partidas_2fase_classificacao`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=547;

--
-- AUTO_INCREMENT de tabela `partidas_2fase_eliminatorias`
--
ALTER TABLE `partidas_2fase_eliminatorias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=89;

--
-- AUTO_INCREMENT de tabela `partidas_2fase_torneio`
--
ALTER TABLE `partidas_2fase_torneio`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=937;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15789;

--
-- AUTO_INCREMENT de tabela `torneio_grupos`
--
ALTER TABLE `torneio_grupos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1949;

--
-- AUTO_INCREMENT de tabela `torneio_grupo_times`
--
ALTER TABLE `torneio_grupo_times`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6125;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1407;

--
-- AUTO_INCREMENT de tabela `torneio_partidas`
--
ALTER TABLE `torneio_partidas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7001;

--
-- AUTO_INCREMENT de tabela `torneio_solicitacoes`
--
ALTER TABLE `torneio_solicitacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `torneio_times`
--
ALTER TABLE `torneio_times`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=665;

--
-- AUTO_INCREMENT de tabela `torneio_time_integrantes`
--
ALTER TABLE `torneio_time_integrantes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3530;

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
-- Restrições para tabelas `partidas_2fase_classificacao`
--
ALTER TABLE `partidas_2fase_classificacao`
  ADD CONSTRAINT `fk_classificacao_2fase_grupo` FOREIGN KEY (`grupo_id`) REFERENCES `torneio_grupos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_classificacao_2fase_time` FOREIGN KEY (`time_id`) REFERENCES `torneio_times` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_classificacao_2fase_torneio` FOREIGN KEY (`torneio_id`) REFERENCES `torneios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `partidas_2fase_eliminatorias`
--
ALTER TABLE `partidas_2fase_eliminatorias`
  ADD CONSTRAINT `fk_eliminatorias_time1` FOREIGN KEY (`time1_id`) REFERENCES `torneio_times` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_eliminatorias_time2` FOREIGN KEY (`time2_id`) REFERENCES `torneio_times` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_eliminatorias_torneio` FOREIGN KEY (`torneio_id`) REFERENCES `torneios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_eliminatorias_vencedor` FOREIGN KEY (`vencedor_id`) REFERENCES `torneio_times` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `partidas_2fase_torneio`
--
ALTER TABLE `partidas_2fase_torneio`
  ADD CONSTRAINT `fk_partidas_2fase_grupo` FOREIGN KEY (`grupo_id`) REFERENCES `torneio_grupos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_partidas_2fase_time1` FOREIGN KEY (`time1_id`) REFERENCES `torneio_times` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_partidas_2fase_time2` FOREIGN KEY (`time2_id`) REFERENCES `torneio_times` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_partidas_2fase_torneio` FOREIGN KEY (`torneio_id`) REFERENCES `torneios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_partidas_2fase_vencedor` FOREIGN KEY (`vencedor_id`) REFERENCES `torneio_times` (`id`) ON DELETE SET NULL;

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
