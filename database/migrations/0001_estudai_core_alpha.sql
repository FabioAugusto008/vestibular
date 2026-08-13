-- EstudAI core alpha - migration incremental e nao destrutiva
-- Data: 2026-05-15
-- Objetivo: preparar onboarding, planos, rotina, IA e acompanhamento sem apagar dados existentes.

CREATE TABLE IF NOT EXISTS schema_migrations (
  version VARCHAR(80) PRIMARY KEY,
  description VARCHAR(255) NOT NULL,
  applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS estudo_perfis (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL UNIQUE,
  objetivo VARCHAR(60) NOT NULL DEFAULT 'enem',
  data_prova DATE NULL,
  horas_dia DECIMAL(4,2) DEFAULT 1.50,
  dias_semana_json TEXT NULL,
  dificuldades_json TEXT NULL,
  prioridades_json TEXT NULL,
  preferencia_estudo VARCHAR(40) DEFAULT 'misto',
  meta_semanal VARCHAR(160) NULL,
  notificacoes TINYINT(1) DEFAULT 0,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS onboarding_respostas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  versao VARCHAR(40) NOT NULL DEFAULT '0.0.1-alpha',
  respostas_json TEXT NOT NULL,
  concluido_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS planos_estudo (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  titulo VARCHAR(160) NOT NULL,
  origem ENUM('manual','ia','fallback') DEFAULT 'manual',
  status ENUM('rascunho','ativo','pausado','concluido','arquivado') DEFAULT 'rascunho',
  resumo TEXT NULL,
  data_inicio DATE NULL,
  data_fim DATE NULL,
  plano_json TEXT NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS plano_estudo_itens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  plano_id INT NOT NULL,
  usuario_id INT NOT NULL,
  dia_semana VARCHAR(20) NULL,
  data_prevista DATE NULL,
  materia VARCHAR(80) NULL,
  tipo_atividade ENUM('teoria','questoes','revisao','simulado','misto','outro') DEFAULT 'misto',
  tempo_estimado_min INT DEFAULT 30,
  descricao TEXT NULL,
  ordem INT DEFAULT 0,
  status ENUM('pendente','em_andamento','concluido','adiado','cancelado') DEFAULT 'pendente',
  concluido_em DATETIME NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (plano_id) REFERENCES planos_estudo(id) ON DELETE CASCADE,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tarefas_estudo (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  plano_id INT NULL,
  titulo VARCHAR(160) NOT NULL,
  materia VARCHAR(80) NULL,
  tipo ENUM('teoria','questoes','revisao','simulado','anotacao','custom') DEFAULT 'custom',
  data_prevista DATE NULL,
  tempo_estimado_min INT DEFAULT 30,
  status ENUM('pendente','em_andamento','concluida','adiada','cancelada') DEFAULT 'pendente',
  concluida_em DATETIME NULL,
  criada_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  atualizada_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (plano_id) REFERENCES planos_estudo(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rotina_semanal (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  dia_semana ENUM('seg','ter','qua','qui','sex','sab','dom') NOT NULL,
  hora_inicio TIME NULL,
  hora_fim TIME NULL,
  foco VARCHAR(120) NULL,
  ativo TINYINT(1) DEFAULT 1,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_rotina_usuario_dia (usuario_id, dia_semana, hora_inicio),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sessoes_estudo (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  tarefa_id INT NULL,
  materia VARCHAR(80) NULL,
  tipo_atividade ENUM('teoria','questoes','revisao','simulado','misto','outro') DEFAULT 'misto',
  iniciou_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finalizou_em DATETIME NULL,
  duracao_seg INT DEFAULT 0,
  observacoes TEXT NULL,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (tarefa_id) REFERENCES tarefas_estudo(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ia_historico (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  provider VARCHAR(40) NOT NULL DEFAULT 'openrouter',
  modelo VARCHAR(120) NULL,
  tipo ENUM('diagnostico','plano_estudos','mensagem','revisao','outro') DEFAULT 'outro',
  entrada_resumo TEXT NULL,
  resposta_json TEXT NULL,
  status ENUM('sucesso','fallback','erro') DEFAULT 'sucesso',
  erro TEXT NULL,
  prompt_tokens INT DEFAULT 0,
  completion_tokens INT DEFAULT 0,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS preferencias_estudo (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL UNIQUE,
  densidade_interface ENUM('compacta','normal','confortavel') DEFAULT 'normal',
  ultima_secao VARCHAR(60) NULL,
  pwa_instalado TINYINT(1) DEFAULT 0,
  timezone VARCHAR(80) DEFAULT 'America/Sao_Paulo',
  preferencias_json TEXT NULL,
  atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notificacoes_usuario (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  tipo ENUM('lembrete','meta','revisao','simulado','sistema') DEFAULT 'lembrete',
  titulo VARCHAR(160) NOT NULL,
  mensagem TEXT NULL,
  agendada_para DATETIME NULL,
  enviada_em DATETIME NULL,
  lida_em DATETIME NULL,
  status ENUM('pendente','enviada','lida','cancelada','erro') DEFAULT 'pendente',
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS revisoes_programadas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  questao_id INT NULL,
  materia VARCHAR(80) NULL,
  assunto VARCHAR(160) NULL,
  data_revisao DATE NOT NULL,
  intervalo_dias INT DEFAULT 1,
  status ENUM('pendente','concluida','adiada','cancelada') DEFAULT 'pendente',
  concluida_em DATETIME NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (questao_id) REFERENCES questoes(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('0001_estudai_core_alpha', 'Base incremental EstudAI para onboarding, planos, rotina, IA e revisoes.');
