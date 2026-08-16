-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 26-Abr-2026 às 21:24
-- Versão do servidor: 10.4.32-MariaDB
-- versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `pap`
--

-- --------------------------------------------------------

--
-- Estrutura da tabela `arvores`
--

CREATE TABLE `arvores` (
  `id` int(11) NOT NULL,
  `place_name` varchar(100) DEFAULT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `especie` varchar(100) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `tipo_intervencao` varchar(50) DEFAULT NULL,
  `estado` varchar(50) DEFAULT NULL,
  `assigned_to_user_id` int(11) DEFAULT NULL,
  `assigned_by_user_id` int(11) DEFAULT NULL,
  `scheduled_for` datetime DEFAULT NULL,
  `mensagem_funcionario` text DEFAULT NULL,
  `assigned_at` datetime DEFAULT NULL,
  `notification_read` tinyint(1) NOT NULL DEFAULT 0,
  `completed_by_user_id` int(11) DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `reminder_every_days` int(11) DEFAULT 5,
  `next_reminder_at` datetime DEFAULT NULL,
  `last_reminder_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `arvores`
--

INSERT INTO `arvores` (`id`, `place_name`, `latitude`, `longitude`, `especie`, `criado_em`, `tipo_intervencao`, `estado`, `assigned_to_user_id`, `assigned_by_user_id`, `scheduled_for`, `mensagem_funcionario`, `assigned_at`, `notification_read`, `completed_by_user_id`, `completed_at`, `reminder_every_days`, `next_reminder_at`, `last_reminder_at`) VALUES
(1, 'EN 254, Bairro 25 de Abril, Bacelo e Senhora da Saúde, Évora, 7005-278, Portugal', 38.58127900, -7.88486100, 'Carvalho', '2026-04-25 15:34:22', 'Corte', 'Corte Pendente', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 5, NULL, NULL),
(2, 'Pim Teatro | PIMTAÍ, CM 1086, Santa Maria, Malagueira, Malagueira e Horta das Figueiras, Évora, 7000', 38.57714700, -7.92881900, 'Oliveira', '2026-04-25 15:34:36', 'Poda', 'Corte Pendente', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 5, NULL, NULL),
(3, 'Quinta das Tâmaras, Bacelo e Senhora da Saúde, Évora, 7005-863, Portugal', 38.57978900, -7.90726600, 'Pinheiro', '2026-04-25 15:34:53', 'Poda', 'Poda pendente', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 5, NULL, NULL),
(4, 'ER 254, Quinta da Luzerna, Bairro de São José da Ponte, Malagueira e Horta das Figueiras, Évora, 700', 38.55184600, -7.88419600, 'Plátano', '2026-04-25 15:35:06', 'Corte', 'Corte Pendente', 96, 4, '2026-04-30 14:21:00', 'Deve verificar o local e cuidar da árvore.', '2026-04-26 14:20:59', 1, NULL, NULL, 5, '2026-05-01 14:22:00', NULL),
(5, 'Bacelo e Senhora da Saúde, Évora, 7000-782, Portugal', 38.57765600, -7.91322400, 'Jacarandá', '2026-04-25 15:35:17', 'Corte', 'Em Execução', 96, 4, NULL, 'Deve verificar o local e cuidar da árvore.', '2026-04-25 18:52:04', 1, NULL, NULL, 5, '2026-04-30 18:52:04', NULL),
(12, '9, Rua Henrique de Menezes, Horta do Bispo, Horta das Figueiras, Malagueira e Horta das Figueiras, É', 38.55943900, -7.90902900, 'Loureiro', '2026-04-26 19:03:28', 'Corte', 'Em Execução', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 5, NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura da tabela `arvore_relatorios`
--

CREATE TABLE `arvore_relatorios` (
  `id` int(11) NOT NULL,
  `arvore_id` int(11) NOT NULL,
  `funcionario_id` int(11) NOT NULL,
  `mensagem` text NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `atividade`
--

CREATE TABLE `atividade` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `acao` varchar(50) NOT NULL,
  `detalhe` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `comentarios_noticias`
--

CREATE TABLE `comentarios_noticias` (
  `id` int(11) NOT NULL,
  `noticia_id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `texto` text NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `comentarios_noticias`
--

INSERT INTO `comentarios_noticias` (`id`, `noticia_id`, `nome`, `texto`, `user_id`, `criado_em`) VALUES
(1, 4, 'krish', 'yo bro', 1, '2026-04-25 18:14:33'),
(2, 3, 'krishna', 'ola krish sabias que nos podemos falar put aqui', 1, '2026-04-25 18:15:15'),
(3, 2, 'happy', 'nice news', 1, '2026-04-25 18:15:38'),
(4, 1, 'krish', 'hmm', 1, '2026-04-25 18:15:55');

-- --------------------------------------------------------

--
-- Estrutura da tabela `contact`
--

CREATE TABLE `contact` (
  `id` int(11) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `contact`
--

INSERT INTO `contact` (`id`, `name`, `email`, `subject`, `message`, `user_id`, `created_at`) VALUES
(1, 'KRISHNA', 'krishna@email.com', 'Pedido de informação', 'Olá, gostaria de obter mais informações sobre uma ocorrência registada na plataforma.', 0, '2026-04-26 13:10:51'),
(2, 'HAPPY', 'happy@email.com', 'Problema na via pública', 'Boa tarde, venho informar que existe um problema na via pública que necessita de verificação.', 0, '2026-04-26 13:10:51'),
(3, 'KRISH', 'krish@email.com', 'Sugestão para a plataforma', 'Gostaria de sugerir melhorias para tornar a plataforma mais simples e rápida de utilizar.', 0, '2026-04-26 13:10:51');

-- --------------------------------------------------------

--
-- Estrutura da tabela `contact_info`
--

CREATE TABLE `contact_info` (
  `id` int(11) NOT NULL,
  `address` varchar(255) NOT NULL,
  `phone` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `contact_info`
--

INSERT INTO `contact_info` (`id`, `address`, `phone`, `email`, `updated_at`) VALUES
(1, 'Av. Dinis Miranda 116, 7005-140 Évora', '+351 123 456 789', 'Shreesoni520@gmail.com', '2026-02-27 11:50:14');

-- --------------------------------------------------------

--
-- Estrutura da tabela `intervencoes`
--

CREATE TABLE `intervencoes` (
  `id` int(11) NOT NULL,
  `arvore_id` int(11) NOT NULL,
  `state_id` int(11) DEFAULT NULL,
  `tipo` enum('Corte','Poda') NOT NULL,
  `description` text DEFAULT NULL,
  `concluido_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `log`
--

CREATE TABLE `log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `acao` varchar(32) DEFAULT NULL,
  `entidade` varchar(32) DEFAULT NULL,
  `entidade_id` int(11) DEFAULT NULL,
  `detalhes` text DEFAULT NULL,
  `criado_em` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `newsletter_send_tokens`
--

CREATE TABLE `newsletter_send_tokens` (
  `id` int(11) NOT NULL,
  `noticia_id` int(11) NOT NULL,
  `token` char(64) NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `newsletter_subscribers`
--

CREATE TABLE `newsletter_subscribers` (
  `id` int(10) UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL,
  `confirm_token` varchar(64) DEFAULT NULL,
  `is_confirmed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `confirmed_at` datetime DEFAULT NULL,
  `unsubscribed_at` datetime DEFAULT NULL,
  `pending_deletion` tinyint(1) NOT NULL DEFAULT 0,
  `deletion_token` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `newsletter_subscribers`
--

INSERT INTO `newsletter_subscribers` (`id`, `email`, `confirm_token`, `is_confirmed`, `created_at`, `confirmed_at`, `unsubscribed_at`, `pending_deletion`, `deletion_token`) VALUES
(1, 'shreesoni520@gmail.com', NULL, 1, '2026-04-25 16:15:52', NULL, NULL, 0, NULL);

-- --------------------------------------------------------

--
-- Estrutura da tabela `noticias`
--

CREATE TABLE `noticias` (
  `id` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `resumo` text NOT NULL,
  `conteudo` longtext NOT NULL,
  `categoria` enum('plataforma','estrada','outros') NOT NULL DEFAULT 'outros',
  `imagem_lista` varchar(255) DEFAULT NULL,
  `imagem_detalhe` varchar(255) DEFAULT NULL,
  `autor` varchar(100) DEFAULT NULL,
  `data_publicacao` date DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `noticias`
--

INSERT INTO `noticias` (`id`, `titulo`, `resumo`, `conteudo`, `categoria`, `imagem_lista`, `imagem_detalhe`, `autor`, `data_publicacao`, `criado_em`) VALUES
(1, 'Évora acolheu encontro europeu sobre inclusão através das artes', 'Évora acolheu encontro europeu sobre inclusão através das artes', '“Estes momentos são muito mais importantes do que imaginamos. Fazem avançar os projetos e são, de igual modo, momentos de encontros, de partilha de experiências e dificuldades, mas também de oportunidades. Somos todos europeus, partilhamos um território que tem de saber preparar os mais pequenos para o futuro e onde o significado da palavra empatia tem nos dias de hoje particular importância”, considerou a Vereadora da Educação, Carmen Carvalheira, no final da sessão de boas-vindas ao 2º Encontro Internacional “Projeto Erasmus+ Shared Identities”, que teve lugar esta semana, em Évora.\r\n\r\nO encontro foi apoiado pela Câmara Municipal de Évora e reúne docentes, técnicos e artistas num intercâmbio internacional de boas práticas centrado na valorização da diversidade cultural através das artes.\r\n\r\nA receção dos participantes decorreu na manhã de 23 de abril, no Palácio de D. Manuel. Incluiu uma visita guiada ao Centro Interpretativo do Palácio D. Manuel, realizada por Gustavo Val-Flores (Técnico Superior da Divisão de Cultura e Património da Câmara de Évora), onde ficaram a conhecer melhor a história da cidade. Fizeram também uma visita guiada ao Teatro Garcia de Resende, com Maria Marrafa (Atriz do CENDREV), que lhes proporcionou conhecer melhor o trabalho dos artistas e descobrir aquele equipamento que possui uma traça “à italiana” e faz parte da Rota Europeia de Teatros Históricos.\r\n\r\nNo dia 24 de abril, o programa do encontro centrou-se na realização de oficinas artísticas para as turmas da EB da Cruz da Picada com os com professores e artistas internacionais e nacionais. Foi também realizado um workshop de criação de flores para as “As Modas nas Danças de Mastro” dinamizado por Ana Silvestre e Caio Priori dos Santos.\r\n\r\nNo sábado, 25 de abril, está ainda agendado o XIX Encontro de Artistas do MUS-E Portugal com diversas atividades, nomeadamente no Pólo dos Leões/Universidade de Évora.\r\n\r\nRefira-se que o 1º Encontro Internacional do Projeto, tal como este 2º, reuniu docentes, técnicos e artistas num importante intercâmbio de boas práticas em que Portugal esteve representado pela Coordenadora da Escola EB1 da Cruz da Picada, Maria João Coelho, e pelo artista do MUS-E Évora, Caio Priori dos Santos.\r\n\r\nDe acordo com a Associação Yehudi Menuhin Portugal (AYMP), “Shared Identities”, é uma iniciativa “Erasmus+”, que utiliza as artes como ferramenta de inclusão, educação e desenvolvimento pessoal junto de crianças em contexto escolar. Promove práticas artísticas, como música, teatro, dança, artes visuais e tecnologias digitais, como meio de expressão, aprendizagem e construção de identidade.\r\n\r\nÉ coordenado pelo MUS-E Hungria e envolve parceiros de Portugal, Espanha e Alemanha. Decorre entre 2025 e 2027 e dirige-se a crianças dos 6 aos 11 anos que enfrentam diferentes desafios sociais, culturais ou educativos.\r\n\r\nO projeto é desenvolvido no nosso país pela Associação Yehudi Menuhin Portugal (AYMP), em colaboração com o Agrupamento de Escolas Manuel Ferreira Patrício, em Évora. Pode conhecer mais sobre este trabalho de relevante interesse público em aymp.pt em www.instagram.com/mus_e.pt/ e também em www.facebook.com/AssociacaoYehudiMenuhinPortugal/', 'outros', 'uploads/noticias/noticia_1_lista_1777136390.webp', 'uploads/noticias/noticia_1_det_0_1777137142.webp', '...', '2026-04-24', '2026-04-25 17:59:50'),
(2, 'Assembleia Municipal Jovem de Évora deu voz aos alunos do Concelho', 'Assembleia Municipal Jovem de Évora deu voz aos alunos do Concelho', 'O envolvimento ativo dos jovens eborenses ficou bem patente através da sua ampla participação na sessão da Assembleia Municipal Jovem de Évora que está a decorrer esta tarde, 22 de abril de 2026, nos Paços do Concelho.\r\n\r\n \r\nAtravés do exercício de uma cidadania ativa, os alunos dos agrupamentos de escolas do Concelho expressam ideias, escutam os outros, debatem e propõem soluções, participando deste modo nos processos de decisão política e incentivando a reflexão crítica sobre as suas necessidades e também da comunidade local.\r\n\r\n\r\nA sessão começou as boas-vindas dadas pelo Presidente da Assembleia Municipal de Évora, Jorge Araújo e pelo Presidente da Câmara Municipal de Évora, Carlos Zorrinho. Ambos os autarcas sublinharam a importância de cumprir as regras democráticas e também da participação da população na vida política, nomeadamente dos jovens.\r\n\r\n\r\n“O futuro é aquilo que nós fazemos dele. De alguma maneira, convoca-nos a fazer parte e vocês estão aqui a assumi-lo”, afirmou Carlos Zorrinho. O autarca destacou também a importância da Revolução de 25 de Abril “que permitiu a democracia e a liberdade” e o facto de “estarmos hoje aqui nesta que é a casa da democracia do nosso concelho”.\r\n\r\n \r\nAntes da Ordem do Dia, o Presidente Jorge Araújo, deixou também o convite a todos para estarem presentes na sessão solene da Assembleia Municipal de Évora dedicada ao tema “50 Anos das Primeiras Eleições Autárquicas Democráticas”, que tem lugar no dia 25 de abril (sábado), a partir das 11 horas, no Salão Nobre dos Paços do Concelho.\r\n\r\n\r\n\r\nCom a eleição dos Secretários para a Mesa da Assembleia, seguiu-se a intervenção dos estudantes do Agrupamento de Escolas Severim de Faria que propuseram a moção: “Comemoração dos 50 Anos da Constituição de 1976” e duas recomendações: “Recolha mais eficaz e eficiente do lixo” e “Pressionar a Universidade de Évora para apresentar uma oferta mais variada nas áreas das Ciências Sociais e Humanidades, como forma de garantir a manutenção dos jovens eborenses na cidade, após o fim do ensino secundário”.\r\n\r\n\r\n\r\nDos trabalhos fazem ainda parte a apresentação, pelo Agrupamento de Escolas Gabriel Pereira, da “Moção de congratulação pelos 50 anos da aprovação da Constituição da República Portuguesa em 2 de abril de 1976 e pela entrada em vigor a 25 de abril de 1976 e da “Moção de congratulação pelos 40 anos da adesão de Portugal à CEE”.\r\n\r\n\r\n\r\nNos temas da Ordem do Dia, o Agrupamento de Escolas André de Gouveia trará a debate “Évora em Movimento – Sistema de Bicicletas Públicas Partilhadas” e o Agrupamento de Escolas Severim de Faria centrar-se-á nos temas: “Segurança rodoviária, em especial para os jovens” e “Promoção de habitação a custo controlado e/ou disponibilização de casas com rendas acessíveis aos jovens, de forma a possibilitar a sua fixação no Concelho”.\r\n\r\n \r\nPor seu turno, o Agrupamento de Escolas Gabriel Pereira colocará o foco nas questões ambientais e sustentabilidade, falta de Habitação acessível, desertificação do Centro Histórico e reduzida oferta de atividades culturais e de lazer para os jovens ao longo do ano.', 'outros', 'uploads/noticias/noticia_2_lista_1777136520.webp', 'uploads/noticias/noticia_2_det_0_1777136520.webp', '...', '2026-04-24', '2026-04-25 18:02:00'),
(3, 'CLAS de Évora promove apresentação de respostas sociais locais', 'CLAS de Évora promove apresentação de respostas sociais locais', 'O Conselho Local de Ação Social de Évora reuniu na tarde de 21 de abril, numa sessão dedicada à apresentação de respostas e recursos existentes no concelho no domínio da intervenção social.\r\n\r\nA iniciativa contou com a presença e mediação da Presidente do CLAS, Vereadora Carmen Carvalheira, que destacou a relevância deste momento para reforçar a visibilidade do trabalho desenvolvido pelas organizações locais, bem como para potenciar a articulação, a complementaridade e o estabelecimento de parcerias entre os diferentes agentes.\r\n\r\nA sessão reuniu cerca de 60 participantes, que tiveram a oportunidade de conhecer 17 projetos e respostas em curso no território, abrangendo áreas como a saúde, o envelhecimento, os comportamentos aditivos, o voluntariado, o apoio a pessoas em situação de sem-abrigo e o apoio à pessoa com deficiência, entre outras.\r\n\r\nEstiveram representadas diversas entidades do concelho, nomeadamente a Unidade Local de Saúde do Alentejo Central, a Cáritas Arquidiocesana de Évora, a APPACDM de Évora, a Santa Casa da Misericórdia de Évora, a Universidade de Évora, a Fundação Unitate, a Fundação Eugénio de Almeida, a Raízes Seguras, o Centro Humanitário de Évora da Cruz Vermelha Portuguesa e a Associação Vida Autónoma.\r\n\r\nEsta iniciativa reafirma o compromisso do CLAS de Évora com a promoção do trabalho em rede e com a valorização das respostas sociais locais, contribuindo para uma intervenção mais integrada e eficaz junto da população.', 'outros', 'uploads/noticias/noticia_3_lista_1777136596.webp', 'uploads/noticias/noticia_3_det_0_1777136596.webp', '...', '2026-04-22', '2026-04-25 18:03:16'),
(4, 'Em reunião pública de Câmara 16 de abril de 2026: Executivo aprovou Prestação de Contas de 2025', 'Em reunião pública de Câmara 16 de abril de 2026: Executivo aprovou Prestação de Contas de 2025', 'Foi aprovada a Prestação de Contas de 2025, que será agora enviada à Assembleia Municipal para deliberação na sua reunião de 27 de abril.\r\n\r\nA empreitada de requalificação do Rossio de S. Brás – 1ª fase foi adjudicada.\r\n\r\nFoi aprovada a entidade selecionada (Santa Casa da Misericórdia de Évora) para celebração de protocolo de cooperação destinado à gestão do Centro de Alojamento de Emergência Social 2.0 e Apartamentos Partilhados.\r\n\r\nComo explicou a Vereadora Carmen Carvallheira, este Centro funcionará no edifício municipal do antigo Lar dos Pinheiros e tem capacidade de alojamento para 24 pessoas. É uma resposta de acolhimento de emergência destinada a pessoas em qualquer situação aguda e imprevista, avaliada como ameaçadora e que coloca as mesmas em situação de perigo e desproteção, decorrentes da ausência de condições mínimas de subsistência e exigindo uma resposta imediata.\r\n\r\nMereceu também aprovação o Projeto de Loteamento Municipal de S. Miguel de Machede para registos notariais e a proposta para autorizar o início do procedimento de alteração do Regulamento Municipal de Atribuição de Lotes.\r\n\r\nAprovada igualmente a minuta do Contrato de Cooperação Interadministrativo para reparação geral de exteriores da infraestrutura adstrita à esquadra de trânsito do comando distrital de Évora da PSP.\r\n\r\nA Câmara Municipal de Évora aprovou ainda um conjunto de outras propostas e os seguintes votos: Voto de pesar pelo falecimento de Matias Godinho (proprietário do restaurante “A Curva do Bacelo”; Voto de felicitações à equipa de Sub20 do Lusitano Ginásio Clube que se sagrou campeã distrital e ao clube; Voto de saudação ao Clube de Futebol Eborense pelo seu 50º aniversário; Voto de sudação aos restaurantes “Tua Madre” e “Cozinha do Paço”, distinguidos na Gala Sóis do Guia Repsol 2026; Voto de saudação ao CORUÉ (Universidade de Évora) pelo seu 43º Aniversário; Voto de saudação ao Dia Mundial da Saúde; e Moção de saudação ao 52º Aniversário da Revolução do 25 de Abril.', 'outros', 'uploads/noticias/noticia_4_lista_1777136664.webp', 'uploads/noticias/noticia_4_det_0_1777136664.webp', '...', '2026-04-16', '2026-04-25 18:04:24');

-- --------------------------------------------------------

--
-- Estrutura da tabela `notificacoes`
--

CREATE TABLE `notificacoes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_by_user_id` int(11) DEFAULT NULL,
  `origem_tipo` varchar(50) NOT NULL,
  `origem_id` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `mensagem` text NOT NULL,
  `enviar_em` datetime DEFAULT NULL,
  `lida` tinyint(1) NOT NULL DEFAULT 0,
  `criada_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `ocorrencias`
--

CREATE TABLE `ocorrencias` (
  `id` int(11) NOT NULL,
  `descricao` text NOT NULL,
  `latitude` double NOT NULL,
  `longitude` double NOT NULL,
  `place_name` varchar(255) DEFAULT NULL,
  `tipo_intervencao` varchar(50) NOT NULL,
  `estado` varchar(50) NOT NULL,
  `data_ocorrencia` datetime DEFAULT NULL,
  `criado_em` datetime NOT NULL,
  `user_id` int(11) NOT NULL,
  `imagem` varchar(255) DEFAULT NULL,
  `assigned_to_user_id` int(11) DEFAULT NULL,
  `assigned_by_user_id` int(11) DEFAULT NULL,
  `scheduled_for` datetime DEFAULT NULL,
  `mensagem_funcionario` text DEFAULT NULL,
  `assigned_at` datetime DEFAULT NULL,
  `notification_read` tinyint(1) NOT NULL DEFAULT 0,
  `reminder_every_days` int(11) DEFAULT NULL,
  `next_reminder_at` datetime DEFAULT NULL,
  `last_reminder_at` datetime DEFAULT NULL,
  `completed_by_user_id` int(11) DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `ocorrencias`
--

INSERT INTO `ocorrencias` (`id`, `descricao`, `latitude`, `longitude`, `place_name`, `tipo_intervencao`, `estado`, `data_ocorrencia`, `criado_em`, `user_id`, `imagem`, `assigned_to_user_id`, `assigned_by_user_id`, `scheduled_for`, `mensagem_funcionario`, `assigned_at`, `notification_read`, `reminder_every_days`, `next_reminder_at`, `last_reminder_at`, `completed_by_user_id`, `completed_at`) VALUES
(1, 'Árvore de grande porte caída e partida, a bloquear parcialmente o caminho pedonal. Necessita de remoção urgente para garantir a segurança dos peões.', 38.56016, -7.919001, 'Complexo Desportivo de Évora, Rua Sousa Brandão, Vila Lusitano, Horta das Figueiras, Malagueira e Horta das Figueiras, Évora, 7005-625, Portugal', 'Corte', 'Em Execução', '2026-04-25 00:00:00', '2026-04-26 08:56:00', 4, 'ocorrencia_1777190160_4.jpg', 96, 4, NULL, 'Deve verificar o local e resolver a ocorrência atribuída.', '2026-04-26 15:51:39', 1, 5, '2026-05-01 15:53:00', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura da tabela `ocorrencias_estrada`
--

CREATE TABLE `ocorrencias_estrada` (
  `id` int(11) NOT NULL,
  `descricao` text NOT NULL,
  `latitude` double NOT NULL,
  `longitude` double NOT NULL,
  `place_name` varchar(255) DEFAULT NULL,
  `tipo_intervencao` varchar(50) NOT NULL,
  `data_ocorrencia` datetime DEFAULT NULL,
  `criado_em` datetime NOT NULL,
  `user_id` int(11) NOT NULL,
  `imagem` varchar(255) DEFAULT NULL,
  `estado` varchar(100) NOT NULL DEFAULT 'Por tratar',
  `assigned_to_user_id` int(11) DEFAULT NULL,
  `assigned_by_user_id` int(11) DEFAULT NULL,
  `scheduled_for` datetime DEFAULT NULL,
  `mensagem_funcionario` text DEFAULT NULL,
  `assigned_at` datetime DEFAULT NULL,
  `notification_read` tinyint(1) NOT NULL DEFAULT 0,
  `reminder_every_days` int(11) DEFAULT NULL,
  `next_reminder_at` datetime DEFAULT NULL,
  `last_reminder_at` datetime DEFAULT NULL,
  `completed_by_user_id` int(11) DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `ocorrencias_estrada`
--

INSERT INTO `ocorrencias_estrada` (`id`, `descricao`, `latitude`, `longitude`, `place_name`, `tipo_intervencao`, `data_ocorrencia`, `criado_em`, `user_id`, `imagem`, `estado`, `assigned_to_user_id`, `assigned_by_user_id`, `scheduled_for`, `mensagem_funcionario`, `assigned_at`, `notification_read`, `reminder_every_days`, `next_reminder_at`, `last_reminder_at`, `completed_by_user_id`, `completed_at`) VALUES
(1, 'Pavimento da estrada danificado, com pedras da calçada soltas e buraco com acumulação de água junto à passadeira. Situação perigosa para veículos e peões, necessitando de reparação urgente.', 38.56694, -7.914107, 'Rua João Cutileiro, Horta do Bispo, Horta das Figueiras, Malagueira e Horta das Figueiras, Évora, 7005-206, Portugal', 'Buraco na estrada', '2026-04-10 00:00:00', '2026-04-26 09:05:06', 4, 'ocorrencia_estrada_1777190706_4.jpg', 'Em análise', 96, 4, NULL, 'Deve verificar o local e resolver a ocorrência de estrada atribuída.', '2026-04-26 17:10:36', 1, 5, '2026-05-01 17:11:00', NULL, NULL, NULL),
(2, 'Estrada com vários buracos no pavimento e acumulação de água, colocando em risco a circulação dos veículos. Necessita de reparação urgente para evitar acidentes e danos nas viaturas.', 38.56508, -7.903643, 'Rua Arquiteto Manuel Tierno Bagulho, Tapada do Matias, Horta das Figueiras, Malagueira e Horta das Figueiras, Évora, 7005-636, Portugal', 'Pavimento degradado', '2026-04-01 00:00:00', '2026-04-26 09:13:33', 4, 'ocorrencia_estrada_1777191213_4.jpg', 'Em execução', 96, 4, '2026-05-10 16:00:00', 'Deve verificar o local e resolver a ocorrência de estrada atribuída.', '2026-04-26 17:00:09', 1, 5, '2026-05-01 17:02:00', NULL, NULL, NULL),
(3, 'Sinal de trânsito caído no passeio, junto a um edifício, dificultando a visibilidade da sinalização e podendo causar perigo para peões. Necessita de recolocação e reparação urgente.', 38.562382, -7.919649, 'Ciclovia N380, Vila Lusitano, Horta das Figueiras, Malagueira e Horta das Figueiras, Évora, 7005-496, Portugal', 'Sinalização danificada', '2026-04-10 00:00:00', '2026-04-26 09:16:57', 4, 'ocorrencia_estrada_1777191417_4.jpg', 'Por tratar', 96, 4, '2026-05-10 16:00:00', 'Deve verificar o local e resolver a ocorrência de estrada atribuída.', '2026-04-26 17:00:09', 1, 5, '2026-05-01 17:02:00', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura da tabela `ocorrencia_estrada_relatorios`
--

CREATE TABLE `ocorrencia_estrada_relatorios` (
  `id` int(11) NOT NULL,
  `ocorrencia_estrada_id` int(11) NOT NULL,
  `funcionario_id` int(11) NOT NULL,
  `mensagem` text NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `ocorrencia_relatorios`
--

CREATE TABLE `ocorrencia_relatorios` (
  `id` int(11) NOT NULL,
  `ocorrencia_id` int(11) NOT NULL,
  `funcionario_id` int(11) NOT NULL,
  `mensagem` text NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `pending_public_email_verifications`
--

CREATE TABLE `pending_public_email_verifications` (
  `id` int(11) UNSIGNED NOT NULL,
  `user_id` int(11) UNSIGNED NOT NULL,
  `old_email` varchar(255) NOT NULL,
  `new_email` varchar(255) NOT NULL,
  `code` varchar(10) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `pending_user_email_changes`
--

CREATE TABLE `pending_user_email_changes` (
  `id` int(11) UNSIGNED NOT NULL,
  `user_id` int(11) UNSIGNED NOT NULL,
  `new_email` varchar(255) NOT NULL,
  `new_username` varchar(100) NOT NULL,
  `new_name` varchar(80) NOT NULL,
  `new_birthday` date NOT NULL,
  `new_gender` enum('male','female','other') NOT NULL,
  `new_phone` varchar(20) NOT NULL,
  `new_password_hash` varchar(255) DEFAULT NULL,
  `code` varchar(10) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `pending_user_verifications`
--

CREATE TABLE `pending_user_verifications` (
  `id` int(11) UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `name` varchar(80) NOT NULL,
  `birthday` date NOT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `phone` varchar(20) NOT NULL,
  `code` varchar(10) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `pending_user_verifications`
--

INSERT INTO `pending_user_verifications` (`id`, `email`, `username`, `password_hash`, `name`, `birthday`, `gender`, `phone`, `code`, `expires_at`, `used`, `created_at`) VALUES
(1, 'shreesoni520@gmail.com', 'soni', '$2y$10$zxLQvb3Ottno7EdqBc2zJeUNGValP83AE1kA1lovKmnzqpPL.dZZS', 'shree', '2007-09-22', 'male', '+351920263262', '201067', '2026-04-25 17:11:18', 1, '2026-04-25 17:01:18'),
(2, 'shreekrishna.soni520@gmail.com', 'soni', '$2y$10$z1pkDDpTgX3nY8sMMmxtMOFyEWqev05jF5Lds1pAF2jv8qLZzU2xG', 'soni', '2007-09-22', 'male', '+351920262263', '564258', '2026-04-25 17:16:59', 1, '2026-04-25 17:06:59'),
(3, 'rhqef@gmail.com', 'srhgs', '$2y$10$gX5VoThVUXekz4cNF9OPnOI822oQJMGw5zRCAlNGobwVwFYxCjkCi', 'rhgswgh', '2004-03-03', 'male', '+351920265487', '686743', '2026-04-26 17:33:33', 1, '2026-04-26 17:23:33');

-- --------------------------------------------------------

--
-- Estrutura da tabela `states`
--

CREATE TABLE `states` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `color_name` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `states`
--

INSERT INTO `states` (`id`, `name`, `color_name`) VALUES
(92, 'Em Execução', '#b3ceea'),
(93, 'Corte Pendente', '#f94e4e'),
(94, 'Poda pendente', '#b3b3b2'),
(95, 'Concluída', '#6ef46c');

-- --------------------------------------------------------

--
-- Estrutura da tabela `users`
--

CREATE TABLE `users` (
  `id` int(11) UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL,
  `twofa_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `name` varchar(80) DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_activity` datetime DEFAULT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `twofa_email_enabled` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `users`
--

INSERT INTO `users` (`id`, `email`, `twofa_enabled`, `username`, `password`, `created_at`, `name`, `birthday`, `gender`, `photo`, `phone`, `is_active`, `last_activity`, `is_admin`, `twofa_email_enabled`) VALUES
(4, 'shreesoni520@gmail.com', 0, 'admin', '$2y$10$PS2Y1wWlltsYpEPrGadpsuZAO8e4HEP44G3DfZ5PnS0s7VCq8F1Ba', '2025-10-22 09:29:30', 'Shree', '1980-01-22', 'male', 'uploads/fotos/user_4_1773142542.png', '+351920263262', 1, '2026-04-26 19:37:11', 1, 0),
(96, 'shreekrishna.soni520@gmail.com', 0, 'soni', '$2y$10$z1pkDDpTgX3nY8sMMmxtMOFyEWqev05jF5Lds1pAF2jv8qLZzU2xG', '2026-04-25 16:07:27', 'Soni', '2007-09-22', 'male', NULL, '+351920262263', 1, '2026-04-26 19:45:00', 0, 0);

-- --------------------------------------------------------

--
-- Estrutura da tabela `users_public`
--

CREATE TABLE `users_public` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(80) NOT NULL,
  `email` varchar(255) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `twofa_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `phone` varchar(20) DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `users_public`
--

INSERT INTO `users_public` (`id`, `nome`, `email`, `username`, `password_hash`, `twofa_enabled`, `phone`, `birthday`, `gender`, `photo`, `criado_em`) VALUES
(1, 'Shree', 'shreesoni520@gmail.com', 'soni', '$2y$10$zxLQvb3Ottno7EdqBc2zJeUNGValP83AE1kA1lovKmnzqpPL.dZZS', 0, '+351920263262', '2007-09-22', 'male', 'uploads/fotos_public/userpub_1_1777132922.png', '2026-04-25 16:01:31');

-- --------------------------------------------------------

--
-- Estrutura da tabela `user_password_resets_public`
--

CREATE TABLE `user_password_resets_public` (
  `id` int(11) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `token` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `user_twofa_codes`
--

CREATE TABLE `user_twofa_codes` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `code` varchar(10) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `user_twofa_codes_public`
--

CREATE TABLE `user_twofa_codes_public` (
  `id` int(11) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `code` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Índices para tabelas despejadas
--

--
-- Índices para tabela `arvores`
--
ALTER TABLE `arvores`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `arvore_relatorios`
--
ALTER TABLE `arvore_relatorios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `arvore_id` (`arvore_id`),
  ADD KEY `funcionario_id` (`funcionario_id`);

--
-- Índices para tabela `comentarios_noticias`
--
ALTER TABLE `comentarios_noticias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `noticia_id` (`noticia_id`);

--
-- Índices para tabela `contact`
--
ALTER TABLE `contact`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `contact_info`
--
ALTER TABLE `contact_info`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `intervencoes`
--
ALTER TABLE `intervencoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tree_id` (`arvore_id`),
  ADD KEY `state_id` (`state_id`);

--
-- Índices para tabela `log`
--
ALTER TABLE `log`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `newsletter_send_tokens`
--
ALTER TABLE `newsletter_send_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`);

--
-- Índices para tabela `newsletter_subscribers`
--
ALTER TABLE `newsletter_subscribers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Índices para tabela `noticias`
--
ALTER TABLE `noticias`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `notificacoes`
--
ALTER TABLE `notificacoes`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `ocorrencias`
--
ALTER TABLE `ocorrencias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_completed_by_user_id` (`completed_by_user_id`);

--
-- Índices para tabela `ocorrencias_estrada`
--
ALTER TABLE `ocorrencias_estrada`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `ocorrencia_estrada_relatorios`
--
ALTER TABLE `ocorrencia_estrada_relatorios`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `ocorrencia_relatorios`
--
ALTER TABLE `ocorrencia_relatorios`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `pending_public_email_verifications`
--
ALTER TABLE `pending_public_email_verifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `new_email` (`new_email`);

--
-- Índices para tabela `pending_user_email_changes`
--
ALTER TABLE `pending_user_email_changes`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `pending_user_verifications`
--
ALTER TABLE `pending_user_verifications`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `states`
--
ALTER TABLE `states`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `uniq_phone` (`phone`);

--
-- Índices para tabela `users_public`
--
ALTER TABLE `users_public`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_public_email` (`email`),
  ADD UNIQUE KEY `uq_users_public_username` (`username`),
  ADD UNIQUE KEY `uq_users_public_phone` (`phone`);

--
-- Índices para tabela `user_password_resets_public`
--
ALTER TABLE `user_password_resets_public`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_user` (`user_id`);

--
-- Índices para tabela `user_twofa_codes`
--
ALTER TABLE `user_twofa_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Índices para tabela `user_twofa_codes_public`
--
ALTER TABLE `user_twofa_codes_public`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_code` (`user_id`,`code`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- AUTO_INCREMENT de tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `arvores`
--
ALTER TABLE `arvores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de tabela `arvore_relatorios`
--
ALTER TABLE `arvore_relatorios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `comentarios_noticias`
--
ALTER TABLE `comentarios_noticias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `contact`
--
ALTER TABLE `contact`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `contact_info`
--
ALTER TABLE `contact_info`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `intervencoes`
--
ALTER TABLE `intervencoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `log`
--
ALTER TABLE `log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `newsletter_send_tokens`
--
ALTER TABLE `newsletter_send_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `newsletter_subscribers`
--
ALTER TABLE `newsletter_subscribers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `noticias`
--
ALTER TABLE `noticias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de tabela `notificacoes`
--
ALTER TABLE `notificacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `ocorrencias`
--
ALTER TABLE `ocorrencias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `ocorrencias_estrada`
--
ALTER TABLE `ocorrencias_estrada`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `ocorrencia_estrada_relatorios`
--
ALTER TABLE `ocorrencia_estrada_relatorios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `ocorrencia_relatorios`
--
ALTER TABLE `ocorrencia_relatorios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pending_public_email_verifications`
--
ALTER TABLE `pending_public_email_verifications`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pending_user_email_changes`
--
ALTER TABLE `pending_user_email_changes`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pending_user_verifications`
--
ALTER TABLE `pending_user_verifications`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `states`
--
ALTER TABLE `states`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=121;

--
-- AUTO_INCREMENT de tabela `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=98;

--
-- AUTO_INCREMENT de tabela `users_public`
--
ALTER TABLE `users_public`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `user_password_resets_public`
--
ALTER TABLE `user_password_resets_public`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `user_twofa_codes`
--
ALTER TABLE `user_twofa_codes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `user_twofa_codes_public`
--
ALTER TABLE `user_twofa_codes_public`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restrições para despejos de tabelas
--

--
-- Limitadores para a tabela `comentarios_noticias`
--
ALTER TABLE `comentarios_noticias`
  ADD CONSTRAINT `comentarios_noticias_ibfk_1` FOREIGN KEY (`noticia_id`) REFERENCES `noticias` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `intervencoes`
--
ALTER TABLE `intervencoes`
  ADD CONSTRAINT `intervencoes_ibfk_1` FOREIGN KEY (`arvore_id`) REFERENCES `arvores` (`id`),
  ADD CONSTRAINT `intervencoes_ibfk_2` FOREIGN KEY (`arvore_id`) REFERENCES `arvores` (`id`),
  ADD CONSTRAINT `intervencoes_ibfk_3` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`);

--
-- Limitadores para a tabela `user_password_resets_public`
--
ALTER TABLE `user_password_resets_public`
  ADD CONSTRAINT `fk_reset_public_user` FOREIGN KEY (`user_id`) REFERENCES `users_public` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `user_twofa_codes`
--
ALTER TABLE `user_twofa_codes`
  ADD CONSTRAINT `fk_twofa_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `user_twofa_codes_public`
--
ALTER TABLE `user_twofa_codes_public`
  ADD CONSTRAINT `fk_twofa_public_user` FOREIGN KEY (`user_id`) REFERENCES `users_public` (`id`) ON DELETE CASCADE;

DELIMITER $$
--
-- Eventos
--
CREATE DEFINER=`root`@`localhost` EVENT `limpar_atividade_48h` ON SCHEDULE EVERY 1 MINUTE STARTS '2026-01-16 20:29:21' ON COMPLETION NOT PRESERVE ENABLE DO DELETE FROM atividade
  WHERE created_at < (NOW() - INTERVAL 1 MINUTE)$$

CREATE DEFINER=`root`@`localhost` EVENT `cleanupnewsletterarchive` ON SCHEDULE EVERY 1 DAY STARTS '2026-02-06 23:07:31' ON COMPLETION NOT PRESERVE ENABLE DO DELETE FROM newsletterarchive
  WHERE archivedat < DATE_SUB(NOW(), INTERVAL 2 MONTH)$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
