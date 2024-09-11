-- MySQL Workbench Forward Engineering

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- -----------------------------------------------------
-- Schema mydb
-- -----------------------------------------------------
-- -----------------------------------------------------
-- Schema improov
-- -----------------------------------------------------

-- -----------------------------------------------------
-- Schema improov
-- -----------------------------------------------------
CREATE SCHEMA IF NOT EXISTS `improov` DEFAULT CHARACTER SET utf8mb4 ;
USE `improov` ;

-- -----------------------------------------------------
-- Table `improov`.`funcao`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `improov`.`funcao` (
  `idfuncao` INT NOT NULL AUTO_INCREMENT,
  `nome_funcao` VARCHAR(45) NOT NULL,
  PRIMARY KEY (`idfuncao`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `improov`.`colaborador`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `improov`.`colaborador` (
  `idcolaborador` INT NOT NULL AUTO_INCREMENT,
  `nome_colaborador` VARCHAR(45) NOT NULL,
  PRIMARY KEY (`idcolaborador`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `improov`.`cliente`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `improov`.`cliente` (
  `idcliente` INT NOT NULL AUTO_INCREMENT,
  `nome_cliente` VARCHAR(45) NOT NULL,
  PRIMARY KEY (`idcliente`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `improov`.`obra`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `improov`.`obra` (
  `idobra` INT NOT NULL AUTO_INCREMENT,
  `nome_obra` VARCHAR(45) NOT NULL,
  PRIMARY KEY (`idobra`))
ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `improov`.`imagens_cliente_obra`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS imagens_cliente_obra (
    idimagens_cliente_obra INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    obra_id INT NOT NULL,
    imagem_nome VARCHAR(255) NOT NULL,
    recebimento_arquivos DATE,
    data_inicio DATE,
    prazo DATE,
    tipo_imagem VARCHAR(55),
	status_id INT,
    FOREIGN KEY (cliente_id) REFERENCES cliente(idcliente),
    FOREIGN KEY (obra_id) REFERENCES obra(idobra)
);

-- -----------------------------------------------------
-- Table `improov`.`status_imagem`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS status_imagem (
	idstatus INT AUTO_INCREMENT PRIMARY KEY,
	nome_status VARCHAR(50))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `improov`.`funcao_imagem`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS funcao_imagem (
    idfuncao_imagem INT AUTO_INCREMENT PRIMARY KEY,
    imagem_id INT NOT NULL,
    colaborador_id INT NOT NULL,
    funcao_id INT NOT NULL,
    prazo DATE, 
    status VARCHAR(50),
    observacao VARCHAR(255),
    FOREIGN KEY (imagem_id) REFERENCES imagens_cliente_obra(idimagens_cliente_obra),
    FOREIGN KEY (colaborador_id) REFERENCES colaborador(idcolaborador),
    FOREIGN KEY (funcao_id) REFERENCES funcao(idfuncao),
    UNIQUE KEY unique_funcao_imagem (imagem_id, funcao_id)
);

-- -----------------------------------------------------
-- Table `improov`.`pos_producao`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS pos_producao (
	idpos_producao INT AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT NOT NULL,
    cliente_id INT NOT NULL,
    obra_id INT NOT NULL,
    data_pos DATE,
    imagem_id INT NOT NULL, 
    caminho_pasta VARCHAR(250),
    numero_bg VARCHAR(50),
    refs VARCHAR(100),
    obs VARCHAR(100),
    status_pos BOOLEAN,
    status_id INT,
	FOREIGN KEY (imagem_id) REFERENCES imagens_cliente_obra(idimagens_cliente_obra),
    FOREIGN KEY (colaborador_id) REFERENCES colaborador(idcolaborador),
    FOREIGN KEY (cliente_id) REFERENCES cliente (idcliente),
    FOREIGN KEY (obra_id) REFERENCES obra (idobra),
    FOREIGN KEY (status_id) REFERENCES status_imagem (idstatus)
);

INSERT INTO funcao (nome_funcao) VALUES ('Caderno');
INSERT INTO funcao (nome_funcao) VALUES ('Modelagem');
INSERT INTO funcao (nome_funcao) VALUES ('Composição');
INSERT INTO funcao (nome_funcao) VALUES ('Finalização');
INSERT INTO funcao (nome_funcao) VALUES ('Pós-produção');
INSERT INTO funcao (nome_funcao) VALUES ('Planta Humaniza');



DELIMITER //

CREATE FUNCTION inserir_colaborador() RETURNS INT
DETERMINISTIC
BEGIN

	INSERT INTO colaborador (nome_colaborador) values ('Nicolle'), ('Caio'), ('Luiz'), ('Mariana'), ('Marcelo'), ('Bruna'), ('Anderson'), ('Marcio'), ('André'), ('Stefhanie'), ('Sérgio'), ('Andressa'), ('André Tavares'), ('Adriana');

	RETURN 1;
    END //
    
    
DELIMITER //

CREATE FUNCTION inserir_clientes() RETURNS INT
DETERMINISTIC
BEGIN 
	
    INSERT INTO cliente (nome_cliente) values 
    ('MSA'),
    ('RDO'),
    ('PHA'),
    ('AYA'),
    ('HSA'),
    ('ZIM'),
    ('DOM'),
    ('AVVY'),
    ('INO'),
    ('EDI'),
    ('SIL');
    
    RETURN 1;
    END//
    
DELIMITER //

CREATE FUNCTION inserir_obras() RETURNS INT
DETERMINISTIC
BEGIN 
	
    INSERT INTO obra (nome_obra) values 
    ('SQU'),
    ('VIL'),
    ('NET'),
    ('CAS'),
    ('WIN'),
    ('AMO'),
    ('MOR'),
    ('JATO'),
    ('YACHT'),
    ('236'),
    ('LIN'),
	('TAB'),
    ('ONE'),
    ('ELI');
    
    RETURN 1;
    END//
    
    DELIMITER //

    CREATE FUNCTION inserir_status() RETURNS INT
DETERMINISTIC
BEGIN 
	
    INSERT INTO status_imagem (nome_status) values 
    ('P00'),
    ('R00'),
    ('R01'),
    ('R02'),
    ('R03'),
    ('EF');
    
    RETURN 1;
    END//
    
    
    
    DELIMITER //

CREATE FUNCTION inserir_imagens() RETURNS int
DETERMINISTIC
BEGIN

INSERT INTO imagens_cliente_obra (cliente_id, obra_id, imagem_nome, recebimento_arquivos, data_inicio, prazo) values

	(1, 1, '1. MSA_SQU Fotomontagem aérea com inserção do empreendimento em terreno real ângulo 1', '2024-09-07', '2024-09-07', '2024-09-02'),
	(1, 1, '2. MSA_SQU Fotomontagem aérea com inserção do empreendimento em terreno real ângulo 2', '2024-09-07', '2024-09-07', '2024-09-02'),
	(1, 1, '3. MSA_SQU Fachada no ângulo do observador', '2024-09-07', '2024-09-07', '2024-09-02'),
	(1, 1, '4. MSA_SQU Embasamento mostrando área comercial', '2024-09-07', '2024-09-07', '2024-09-02'),
	(1, 1, '5. MSA_SQU Hall de entrada', '2024-09-07', '2024-09-07', '2024-09-02'),
	(1, 1, '6. MSA_SQU Salão de festas a definir (1 ou 2) Qual ficar maior e melhor', '2024-09-07', '2024-09-07', '2024-09-02'),
	(1, 1, '7. MSA_SQU Sala de jogos / PUB', '2024-09-07', '2024-09-07', '2024-09-02'),
	(1, 1, '8. MSA_SQU Academia', '2024-09-07', '2024-09-07', '2024-09-02'),
	(1, 1, '9. MSA_SQU Quiosque Piscina', '2024-09-07', '2024-09-07', '2024-09-02'),
	(1, 1, '10. MSA_SQU Brinquedoteca', '2024-09-07', '2024-09-07', '2024-09-02'),
	(1, 1, '11. MSA_SQU Piscina', '2024-09-07', '2024-09-07', '2024-09-02'),
	(1, 1, '12. MSA_SQU Lounge Ofurô', '2024-09-07', '2024-09-07', '2024-09-02'),
	(1, 1, '13. MSA_SQU Living do apartamento tipo (unidade à definir)', '2024-09-07', '2024-09-07', '2024-09-02'),
	(1, 1, '14. MSA_SQU Suíte do apartamento tipo (unidade à definir)', '2024-09-07', '2024-09-07', '2024-09-02'),
	(1, 1, '15. MSA_SQU Sacada com vista real do apartamento face rua Dorvalino', '2024-09-07', '2024-09-07', '2024-09-02'),
	(1, 1, '16. MSA_SQU Sacada com vista real do apartamento face rua Dorvalino', '2024-09-07', '2024-09-07', '2024-09-02'),
	(1, 1, '17. MSA_SQU Sacada com vista real do apartamento face rua Dorvalino', '2024-09-07', '2024-09-07', '2024-09-02'),
	(1, 1, '18. MSA_SQU Planta humanizada implantação geral mostrando o pavimento térreo', '2024-09-07', '2024-09-07', '2024-09-02'),
	(1, 1, '19. MSA_SQU Planta humanizada do pavimento lazer', '2024-09-07', '2024-09-07', '2024-09-02'),
	(1, 1, '20. MSA_SQU Planta humanizada do pavimento tipo', '2024-09-07', '2024-09-07', '2024-09-02'),
	(4, 4, '1. AYA_CAS Portaria', '2024-08-21', '2024-08-21', '2024-09-27'),
	(4, 4, '2. AYA_CAS Boulevard entre casas', '2024-08-21', '2024-08-21', '2024-09-27'),
	(4, 4, '3. AYA_CAS Fotomontagem aérea', '2024-08-21', '2024-08-21', '2024-09-27'),
	(4, 4, '4. AYA_CAS Implantação', '2024-08-21', '2024-08-21', '2024-09-27'),
	(4, 4, '5. AYA_CAS Casa fundos', '2024-08-21', '2024-08-21', '2024-09-27'),
	(4, 4, '6. AYA_CAS Living olhando para fora ângulo 1', '2024-08-21', '2024-08-21', '2024-09-27'),
	(4, 4, '7. AYA_CAS Living olhando para fora ângulo 2', '2024-08-21', '2024-08-21', '2024-09-27'),
	(4, 4, '8. AYA_CAS Fachada aérea', '2024-08-21', '2024-08-21', '2024-09-27'),
	(4, 4, '9. AYA_CAS Suíte casa nova olhando para copa das árvores', '2024-08-21', '2024-08-21', '2024-09-27'),
	(2, 2, '1. RDO_VIL Fachada diurna', '2024-07-12', '2024-07-12', '2024-08-28'),
	(2, 2, '2. RDO_VIL Fachada noturna', '2024-07-12', '2024-07-12', '2024-08-28'),
	(2, 2, '3. RDO_VIL Fachada com aproximação do pórtico', '2024-07-12', '2024-07-12', '2024-08-28'),
	(2, 2, '4. RDO_VIL Imagem de drone com a fachada aplicada (temos essa imagem).', '2024-07-12', '2024-07-12', '2024-08-28'),
	(2, 2, '5. RDO_VIL Hall de entrada', '2024-07-12', '2024-07-12', '2024-08-28'),
	(2, 2, '6. RDO_VIL Business', '2024-07-12', '2024-07-12', '2024-08-28'),
	(2, 2, '7. RDO_VIL Salão de festas', '2024-07-12', '2024-07-12', '2024-08-28'),
	(2, 2, '8. RDO_VIL Gourmet', '2024-07-12', '2024-07-12', '2024-08-28'),
	(2, 2, '9. RDO_VIL Varanda', '2024-07-12', '2024-07-12', '2024-08-28'),
	(2, 2, '10. RDO_VIL Pub', '2024-07-12', '2024-07-12', '2024-08-28'),
	(2, 2, '11. RDO_VIL Sala de jogos', '2024-07-12', '2024-07-12', '2024-08-28'),
	(2, 2, '12. RDO_VIL Teens', '2024-07-12', '2024-07-12', '2024-08-28'),
	(2, 2, '13. RDO_VIL Kids', '2024-07-12', '2024-07-12', '2024-08-28'),
	(2, 2, '14. RDO_VIL Play+Petplace (externo)', '2024-07-12', '2024-07-12', '2024-08-28'),
	(2, 2, '15. RDO_VIL Piscina aquecida', '2024-07-12', '2024-07-12', '2024-08-28'),
	(2, 2, '16. RDO_VIL Spa', '2024-07-12', '2024-07-12', '2024-08-28'),
	(2, 2, '17. RDO_VIL Academia', '2024-07-12', '2024-07-12', '2024-08-28'),
	(2, 2, '18. RDO_VIL Market', '2024-07-12', '2024-07-12', '2024-08-28'),
	(2, 2, '19. RDO_VIL Quiosque+quadra', '2024-07-12', '2024-07-12', '2024-08-28'),
	(2, 2, '20. RDO_VIL Fireplace + horta', '2024-07-12', '2024-07-12', '2024-08-28'),
	(2, 2, '21. RDO_VIL Car Care/Vagas verdes', '2024-07-12', '2024-07-12', '2024-08-28'),
	(2, 2, '22. RDO_VIL Bicicletário', '2024-07-12', '2024-07-12', '2024-08-28'),
	(2, 2, '23. RDO_VIL Living de um apartamento', '2024-07-12', '2024-07-12', '2024-08-28'),
	(2, 2, '24. RDO_VIL Planta humanizada do pavimento lazer', '2024-07-12', '2024-07-12', '2024-08-28'),
    (3, 3, '1. PHA_NET Fotomontagem 1', '2024-06-07', '2024-06-07', '2024-08-01'),
	(3, 3, '2. PHA_NET Fotomontagem 2', '2024-06-07', '2024-06-07', '2024-08-01'),
	(3, 3, '3. PHA_NET Fotomontagem foco 20', '2024-06-07', '2024-06-07', '2024-08-01'),
	(3, 3, '4. PHA_NET Fachada observador', '2024-06-07', '2024-06-07', '2024-08-01'),
	(3, 3, '5. PHA_NET Embasamento frontal', '2024-06-07', '2024-06-07', '2024-08-01'),
	(3, 3, '6. PHA_NET Fachada diurna', '2024-06-07', '2024-06-07', '2024-08-01'),
	(3, 3, '7. PHA_NET Fachada angular noturna', '2024-06-07', '2024-06-07', '2024-08-01'),
	(3, 3, '8. PHA_NET Boulevard 1', '2024-06-07', '2024-06-07', '2024-08-01'),
	(3, 3, '9. PHA_NET Boulevard 2', '2024-06-07', '2024-06-07', '2024-08-01'),
	(3, 3, '10. PHA_NET Boulevard Noturno', '2024-06-07', '2024-06-07', '2024-08-01'),
	(3, 3, '11. PHA_NET Hall entrada + mini market', '2024-06-07', '2024-06-07', '2024-08-01'),
	(3, 3, '12. PHA_NET Piscina adulto', '2024-06-07', '2024-06-07', '2024-08-01'),
	(3, 3, '13. PHA_NET Área deck', '2024-06-07', '2024-06-07', '2024-08-01'),
	(3, 3, '14. PHA_NET Piscina infantil', '2024-06-07', '2024-06-07', '2024-08-01'),
	(3, 3, '15. PHA_NET SPA/Sauna', '2024-06-07', '2024-06-07', '2024-08-01'),
	(3, 3, '16. PHA_NET Academia', '2024-06-07', '2024-06-07', '2024-08-01'),
	(3, 3, '17. PHA_NET Brinquedoteca', '2024-06-07', '2024-06-07', '2024-08-01'),
	(3, 3, '18. PHA_NET Salão festas', '2024-06-07', '2024-06-07', '2024-08-01'),
	(3, 3, '19. PHA_NET Gourmet', '2024-06-07', '2024-06-07', '2024-08-01'),
	(3, 3, '20. PHA_NET Lounge gourmet', '2024-06-07', '2024-06-07', '2024-08-01'),
	(3, 3, '21. PHA_NET Lâmina água', '2024-06-07', '2024-06-07', '2024-08-01'),
	(3, 3, '22. PHA_NET Area fogo', '2024-06-07', '2024-06-07', '2024-08-01'),
	(3, 3, '23. PHA_NET Playground', '2024-06-07', '2024-06-07', '2024-08-01'),
	(3, 3, '24. PHA_NET Lounge externo', '2024-06-07', '2024-06-07', '2024-08-01'),
	(3, 3, '25. PHA_NET Coworking', '2024-06-07', '2024-06-07', '2024-08-01'),
	(3, 3, '26. PHA_NET Wine bar', '2024-06-07', '2024-06-07', '2024-08-01'),
	(3, 3, '27. PHA_NET Sala de jogos', '2024-06-07', '2024-06-07', '2024-08-01'),
	(3, 3, '28. PHA_NET Planta baixa lazer', '2024-06-07', '2024-06-07', '2024-08-01'),
	(3, 3, '29. PHA_NET Planta baixa mezanino', '2024-06-07', '2024-06-07', '2024-08-01'),
	(3, 3, '30. PHA_NET Planta baixa 8-19 (Referente ao pavimento 11/12)', '2024-06-07', '2024-06-07', '2024-08-01'),
	(3, 3, '31. PHA_NET Living apto K fechada', '2024-06-07', '2024-06-07', '2024-08-01'),
	(3, 3, '32. PHA_NET Living apto F aberta', '2024-06-07', '2024-06-07', '2024-08-01'),
	(3, 3, '33. PHA_NET Planta baixa 20', '2024-06-07', '2024-06-07', '2024-08-01'),
	(3, 3, '34. PHA_NET Jardim apto J', '2024-06-07', '2024-06-07', '2024-08-01'),
	(3, 3, '35. PHA_NET Planta baixa 21-29 (Referente ao pavimento 26/27)', '2024-06-07', '2024-06-07', '2024-08-01'),
	(3, 3, '36. PHA_NET Living apto G', '2024-06-07', '2024-06-07', '2024-08-01'),
	(3, 3, '37. PHA_NET Planta baixa 30', '2024-06-07', '2024-06-07', '2024-08-01'),
	(3, 3, '38. PHA_NET Planta baixa 31', '2024-06-07', '2024-06-07', '2024-08-01'),
	(3, 3, '39. PHA_NET Living apto Q', '2024-06-07', '2024-06-07', '2024-08-01'),
    (5, 5, '1. HSA_WIN Fotomontagem aérea com inserção da torre em terreno real', '2024-09-05', '2024-09-05', '2024-09-05'),
	(5, 5, '2. HSA_WIN Fachada diurna no angulo do observador (portfólio)', '2024-09-05', '2024-09-05', '2024-09-05'),
    (5, 5, '3. HSA_WIN Fachada noturna', '2024-09-05', '2024-09-05', '2024-09-05'),
    (5, 5, '4. HSA_WIN Playground mostrando Mini Quadra', '2024-09-05', '2024-09-05', '2024-09-05'),
    (5, 5, '5. HSA_WIN Espaço fogo', '2024-09-05', '2024-09-05', '2024-09-05'),
    (5, 5, '6. HSA_WIN Academia', '2024-09-05', '2024-09-05', '2024-09-05'),
    (5, 5, '7. HSA_WIN Gourmet da piscina', '2024-09-05', '2024-09-05', '2024-09-05'),
    (5, 5, '8. HSA_WIN SPA', '2024-09-05', '2024-09-05', '2024-09-05'),
    (5, 5, '9. HSA_WIN Salão de festas', '2024-09-05', '2024-09-05', '2024-09-05'),
    (5, 5, '10. HSA_WIN Sala de jogos', '2024-09-05', '2024-09-05', '2024-09-05'),
    (5, 5, '11. HSA_WIN  Espaço poker', '2024-09-05', '2024-09-05', '2024-09-05'),
    (5, 5, '12. HSA_WIN Espaço kids', '2024-09-05', '2024-09-05', '2024-09-05'),
    (5, 5, '13. HSA_WIN Sauna', '2024-09-05', '2024-09-05', '2024-09-05'),
    (5, 5, '14. HSA_WIN Piscinas - Foco na piscina infantil', '2024-09-05', '2024-09-05', '2024-09-05'),
    (5, 5, '15. HSA_WIN Piscinas - Foco na piscina adulto', '2024-09-05', '2024-09-05', '2024-09-05'),
    (5, 5, '16. HSA_WIN Piscinas - Foco nos ambientes de estar', '2024-09-05', '2024-09-05', '2024-09-05'),
    (5, 5, '17. HSA_WIN Living do apartamento tipo 1 - Geral', '2024-09-05', '2024-09-05', '2024-09-05'),
    (5, 5, '18. HSA_WIN Living do apartamento tipo 1 - Ângulo 2', '2024-09-05', '2024-09-05', '2024-09-05'),
    (5, 5, '19. HSA_WIN Suíte do apartamento tipo 1', '2024-09-05', '2024-09-05', '2024-09-05'),
    (5, 5, '20. HSA_WIN Planta do pavimento lazer', '2024-09-05', '2024-09-05', '2024-09-05'),
    (5, 5, '21. HSA_WIN Planta humanizada do apartamento tipo', '2024-09-05', '2024-09-05', '2024-09-05'),
	(5, 15, '1.HSA_MON Fotomontagem aérea com inserção do empreendimento em terreno real ângulo 1', '2024-09-06', '2024-09-06', '2024-09-06', 'Fachada'),
	(5, 15, '2.HSA_MON Fotomontagem aérea com foco no Lazer do 21° Pavimento', '2024-09-06', '2024-09-06', '2024-09-06', 'Fachada'),
	(5, 15, '3.HSA_MON Fotomontagem aérea com foco no topo da torre e horizonte', '2024-09-06', '2024-09-06', '2024-09-06', 'Fachada'),
	(5, 15, '4.HSA_MON Fachada no ângulo do observador Diurna', '2024-09-06', '2024-09-06', '2024-09-06', 'Fachada'),
	(5, 15, '5.HSA_MON Fachada no ângulo do observador Noturna', '2024-09-06', '2024-09-06', '2024-09-06', 'Fachada'),
	(5, 15, '6.HSA_MON Fachada no ângulo de portfólio', '2024-09-06', '2024-09-06', '2024-09-06', 'Fachada'),
	(5, 15, '7.HSA_MON Hall de entrada', '2024-09-06', '2024-09-06', '2024-09-06', 'Imagem Interna'),
	(5, 15, '8.HSA_MON Bar molhado', '2024-09-06', '2024-09-06', '2024-09-06', 'Fachada'),
	(5, 15, '9.HSA_MON Piscinas ângulo 1', '2024-09-06', '2024-09-06', '2024-09-06', 'Imagem Externa'),
	(5, 15, '10.HSA_MON Piscinas ângulo 2', '2024-09-06', '2024-09-06', '2024-09-06', 'Imagem Externa'),
	(5, 15, '11.HSA_MON Quiosque da piscina', '2024-09-06', '2024-09-06', '2024-09-06', 'Imagem Interna'),
	(5, 15, '12.HSA_MON Fire place', '2024-09-06', '2024-09-06', '2024-09-06', 'Imagem Externa'),
	(5, 15, '13.HSA_MON Playground', '2024-09-06', '2024-09-06', '2024-09-06', 'Imagem Externa'),
	(5, 15, '14.HSA_MON Salão de festas', '2024-09-06', '2024-09-06', '2024-09-06', 'Imagem Interna'),
	(5, 15, '15.HSA_MON Terraço do Salão de festas', '2024-09-06', '2024-09-06', '2024-09-06', 'Imagem Externa'),
	(5, 15, '16.HSA_MON Espaço kids', '2024-09-06', '2024-09-06', '2024-09-06', 'Imagem Externa'),
	(5, 15, '17.HSA_MON Pet Care olhando para espaço Pet', '2024-09-06', '2024-09-06', '2024-09-06', 'Imagem Externa'),
	(5, 15, '18.HSA_MON Academia', '2024-09-06', '2024-09-06', '2024-09-06', 'Imagem Interna'),
	(5, 15, '19.HSA_MON Piscina aquecida', '2024-09-06', '2024-09-06', '2024-09-06', 'Imagem Interna'),
	(5, 15, '20.HSA_MON Sala de jogos', '2024-09-06', '2024-09-06', '2024-09-06', 'Imagem Interna'),
	(5, 15, '21.HSA_MON Ambiente da área de lazer a definir', '2024-09-06', '2024-09-06', '2024-09-06', 'Imagem Interna'),
	(5, 15, '22.HSA_MON Sky Pub', '2024-09-06', '2024-09-06', '2024-09-06', 'Imagem Interna'),
	(5, 15, '23.HSA_MON Espaço kids', '2024-09-06', '2024-09-06', '2024-09-06', 'Imagem Interna'),
	(5, 15, '24.HSA_MON Terraço', '2024-09-06', '2024-09-06', '2024-09-06', 'Imagem Interna'),
	(5, 15, '25.HSA_MON Wine lounge', '2024-09-06', '2024-09-06', '2024-09-06', 'Imagem Interna'),
	(5, 15, '26.HSA_MON Living do apartamento tipo 1 + Sacada', '2024-09-06', '2024-09-06', '2024-09-06', 'Imagem Interna'),
	(5, 15, '27.HSA_MON Vista real do living do apartamento tipo 1', '2024-09-06', '2024-09-06', '2024-09-06', 'Imagem Interna'),
	(5, 15, '28.HSA_MON Sacada gourmet sensação da vista do apartamento tipo 1', '2024-09-06', '2024-09-06', '2024-09-06', 'Imagem Interna'),
	(5, 15, '29.HSA_MON Suíte do apartamento tipo 1', '2024-09-06', '2024-09-06', '2024-09-06', 'Imagem Interna'),
	(5, 15, '30.HSA_MON Living do apartamento Penthouse ângulo 1', '2024-09-06', '2024-09-06', '2024-09-06', 'Imagem Interna'),
	(5, 15, '31.HSA_MON Living do apartamento Penthouse ângulo 2', '2024-09-06', '2024-09-06', '2024-09-06', 'Imagem Interna'),
	(5, 15, '32.HSA_MON Living do apartamento Penthouse foco na vista', '2024-09-06', '2024-09-06', '2024-09-06', 'Imagem Interna'),
	(5, 15, '33.HSA_MON Suíte do apartamento Penthouse', '2024-09-06', '2024-09-06', '2024-09-06', 'Imagem Interna'),
	(5, 15, '34.HSA_MON Suíte do apartamento Penthouse com foco no closet', '2024-09-06', '2024-09-06', '2024-09-06', 'Imagem Interna'),
	(5, 15, '35.HSA_MON Vista da banheira da suíte do apartamento Penthouse', '2024-09-06', '2024-09-06', '2024-09-06', 'Imagem Interna'),
	(5, 15, '36.HSA_MON Planta humanizada implantação geral mostrando o pavimento térreo', '2024-09-06', '2024-09-06', '2024-09-06', 'Planta Humanizada'),
	(5, 15, '37.HSA_MON Planta humanizada do pavimento lazer - 4° Pavimento', '2024-09-06', '2024-09-06', '2024-09-06', 'Planta Humanizada'),
	(5, 15, '38.HSA_MON Planta humanizada do pavimento lazer - 21° Pavimento', '2024-09-06', '2024-09-06', '2024-09-06', 'Planta Humanizada'),
	(5, 15, '39.HSA_MON Planta humanizada do pavimento Tipo', '2024-09-06', '2024-09-06', '2024-09-06', 'Planta Humanizada'),
	(5, 15, '40.HSA_MON Planta humanizada do pavimento Penthouse', '2024-09-06', '2024-09-06', '2024-09-06', 'Planta Humanizada'),
    (12, 16, '1. OTT_EKO Fotomontagem aérea - Inserção do empreendimento em terreno real visto do mar ângulo 1', '2024-09-06', '2024-09-06', '2024-09-06'),
	(12, 16, '2. OTT_EKO Fotomontagem aérea - Inserção do empreendimento em terreno real visto do mar ângulo 2', '2024-09-06', '2024-09-06', '2024-09-06'),
	(12, 16, '3. OTT_EKO Fachada diurna no angulo do observador', '2024-09-06', '2024-09-06', '2024-09-06'),
	(12, 16, '4. OTT_EKO Embasamento com foco no acesso', '2024-09-06', '2024-09-06', '2024-09-06'),
	(12, 16, '5. OTT_EKO Hall de entrada', '2024-09-06', '2024-09-06', '2024-09-06'),
	(12, 16, '6. OTT_EKO Piscina 1 com vista fotográfica real', '2024-09-06', '2024-09-06', '2024-09-06'),
	(12, 16, '7. OTT_EKO Piscina 2 com vista fotográfica real', '2024-09-06', '2024-09-06', '2024-09-06'),
	(12, 16, '8. OTT_EKO Bar da piscina 1', '2024-09-06', '2024-09-06', '2024-09-06'),
	(12, 16, '9. OTT_EKO Academia com sacada com vista para o mar', '2024-09-06', '2024-09-06', '2024-09-06'),
	(12, 16, '10. OTT_EKO Gamming room', '2024-09-06', '2024-09-06', '2024-09-06'),
	(12, 16, '11. OTT_EKO Salão de festas mostrando terraço com vista fotográfica real', '2024-09-06', '2024-09-06', '2024-09-06'),
	(12, 16, '12. OTT_EKO Sacada do Salão de festas com vista fotográfica real', '2024-09-06', '2024-09-06', '2024-09-06'),
	(12, 16, '13. OTT_EKO Living do apartamento tipo final 1 com vista fotográfica real para o mar', '2024-09-06', '2024-09-06', '2024-09-06'),
	(12, 16, '14. OTT_EKO Living do apartamento tipo final 2 ou 3 com vista fotográfica real para o mar', '2024-09-06', '2024-09-06', '2024-09-06'),
	(12, 16, '16. OTT_EKO Living do apartamento cobertura final 1 com vista fotográfica real para o mar', '2024-09-06', '2024-09-06', '2024-09-06'),
	(12, 16, '17. OTT_EKO Área de festas do apartamento cobertura final 1 com vista fotográfica real', '2024-09-06', '2024-09-06', '2024-09-06'),
	(12, 16, '18. OTT_EKO Planta humanizada do pavimento lazer', '2024-09-06', '2024-09-06', '2024-09-06'),
	(12, 16, '19. OTT_EKO Planta humanizada do apartamento tipo 1', '2024-09-06', '2024-09-06', '2024-09-06'),
	(12, 16, '20. OTT_EKO Planta humanizada do apartamento tipo 2', '2024-09-06', '2024-09-06', '2024-09-06'),
	(12, 16, '21. OTT_EKO Planta humanizada do apartamento tipo 3', '2024-09-06', '2024-09-06', '2024-09-06'),
	(12, 16, '22. OTT_EKO Planta humanizada do apartamento cobertura inferior 1', '2024-09-06', '2024-09-06', '2024-09-06'),
	(12, 16, '23. OTT_EKO Planta humanizada do apartamento cobertura superior 1', '2024-09-06', '2024-09-06', '2024-09-06'),
	(12, 16, '24. OTT_EKO Planta humanizada do apartamento cobertura inferior 2', '2024-09-06', '2024-09-06', '2024-09-06'),
	(12, 16, '25. OTT_EKO Planta humanizada do apartamento cobertura superior 2', '2024-09-06', '2024-09-06', '2024-09-06'),
	(12, 16, '26. OTT_EKO Planta humanizada do apartamento cobertura inferior 3', '2024-09-06', '2024-09-06', '2024-09-06'),
	(12, 16, '27. OTT_EKO Planta humanizada do apartamento cobertura superior 3', '2024-09-06', '2024-09-06', '2024-09-06'),
    (7, 7, '1. DOM_MOR Externa aérea / frontal com mar', '2024-09-06', '2024-09-06', '2024-09-06'),
	(7, 7, '2. DOM_MOR Externa Lateral com piscina', '2024-09-06', '2024-09-06', '2024-09-06'),
	(7, 7, '3. DOM_MOR Externa Fundos com acesso rua', '2024-09-06', '2024-09-06', '2024-09-06'),
	(7, 7, '4. DOM_MOR Externa Paisagismo circulações ACESSO', '2024-09-06', '2024-09-06', '2024-09-06'),
	(7, 7, '5. DOM_MOR Externa Paisagismo circulações ao mar', '2024-09-06', '2024-09-06', '2024-09-06'),
	(7, 7, '6. DOM_MOR Externa pet place + ACADEMIA EXTERNA', '2024-09-06', '2024-09-06', '2024-09-06'),
	(7, 7, '7. DOM_MOR Externa piscina + Espaço FOGO', '2024-09-06', '2024-09-06', '2024-09-06'),
	(7, 7, '8. DOM_MOR Externa ESPAÇO BALANÇOS', '2024-09-06', '2024-09-06', '2024-09-06'),
	(7, 7, '9. DOM_MOR Externa da praia olhando para prédio', '2024-09-06', '2024-09-06', '2024-09-06'),
	(7, 7, '10. DOM_MOR Interno Gourmet Mezanino - integrados', '2024-09-06', '2024-09-06', '2024-09-06'),
	(7, 7, '11. DOM_MOR Interno Gourmet Terreo', '2024-09-06', '2024-09-06', '2024-09-06'),
	(7, 7, '12. DOM_MOR Interno Piscina aquecida', '2024-09-06', '2024-09-06', '2024-09-06'),
	(7, 7, '13. DOM_MOR Interno Sauna', '2024-09-06', '2024-09-06', '2024-09-06'),
	(7, 7, '14. DOM_MOR Interno Wine Bar', '2024-09-06', '2024-09-06', '2024-09-06'),
	(7, 7, '15. DOM_MOR Interno Espaço Office', '2024-09-06', '2024-09-06', '2024-09-06'),
	(7, 7, '16. DOM_MOR Interno Academia', '2024-09-06', '2024-09-06', '2024-09-06'),
	(7, 7, '17. DOM_MOR Interno Brinquedoteca', '2024-09-06', '2024-09-06', '2024-09-06'),
	(7, 7, '18. DOM_MOR Apto varanda vista mar - T05 TORRE A', '2024-09-06', '2024-09-06', '2024-09-06'),
	(7, 7, '19. DOM_MOR Apto vista suite - L03 - TORRE B', '2024-09-06', '2024-09-06', '2024-09-06'),
	(7, 7, '20. DOM_MOR Apto sala e varanda vista mar - C05 TORRE B', '2024-09-06', '2024-09-06', '2024-09-06'),
	(7, 7, '21. DOM_MOR Fotomontagem', '2024-09-06', '2024-09-06', '2024-09-06'),
	(7, 7, '22. DOM_MOR Detalhe/conceito espreguiçadeira + piscina', '2024-09-06', '2024-09-06', '2024-09-06'),
	(7, 7, '23. DOM_MOR Detalhe/conceito vista mar da suíte / cortina', '2024-09-06', '2024-09-06', '2024-09-06'),
	(7, 7, '24. DOM_MOR Detalhe/conceito piscina aquecida', '2024-09-06', '2024-09-06', '2024-09-06'),
	(7, 7, '25. DOM_MOR Detalhe/conceito hall entrada', '2024-09-06', '2024-09-06', '2024-09-06'),
	(7, 7, '26. DOM_MOR PLANTA HUMANIZADA DO PAVIMENTO SUBSOLO', '2024-09-06', '2024-09-06', '2024-09-06'),
	(7, 7, '27. DOM_MOR PLANTA HUMANIZADA IMPLANTAÇÃO MOSTRANDO PAVIMENTO DE LAZER', '2024-09-06', '2024-09-06', '2024-09-06'),
	(7, 7, '28. DOM_MOR PLANTA HUMANIZADA DO PAVIMENTO MEZANINO TORRE A JUNTAMENTE COM O 1º PAVIMENTO TORRE B', '2024-09-06', '2024-09-06', '2024-09-06'),
	(7, 7, '29. DOM_MOR PLANTA HUMANIZADA DO 1º PAVIMENTO TORRE A JUNTAMENTE COM O 2º PAVIMENTO TORRE B', '2024-09-06', '2024-09-06', '2024-09-06'),
	(7, 7, '30. DOM_MOR PLANTA HUMANIZADA DO 2º PAVIMENTO TORRE A JUNTAMENTE COM O 3º PAVIMENTO TORRE B', '2024-09-06', '2024-09-06', '2024-09-06'),
	(7, 7, '31. DOM_MOR PLANTA HUMANIZADA DO 3º PAVIMENTO TORRE A JUNTAMENTE COM O MEZANINO TORRE B', '2024-09-06', '2024-09-06', '2024-09-06'),
	(7, 7, '32. DOM_MOR PLANTA HUMANIZADA DO PAVIMENTO DAS COBERTURAS DAS TORRES A E B', '2024-09-06', '2024-09-06', '2024-09-06'),
	(13, 17, '1. FG_TRI Fachada no angulo do observador de baixo - Diurna', '2024-09-11', '2024-09-11', '2024-10-23', 'Fachada'),
	(13, 17, '2. FG_TRI Fotomontagem aérea com fachada vista da BR 101', '2024-09-11', '2024-09-11', '2024-10-23', 'Fachada'),
	(13, 17, '3. FG_TRI Fotomontagem aérea de topo noturna', '2024-09-11', '2024-09-11', '2024-10-23', 'Fachada'),
	(13, 17, '4. FG_TRI Fotomontagem aérea de topo diurna', '2024-09-11', '2024-09-11', '2024-10-23', 'Fachada'),
	(13, 17, '5. FG_TRI Fotomontagem aérea com fachada vista da praia sentido norte - Sunset', '2024-09-11', '2024-09-11', '2024-10-23', 'Fachada'),
	(13, 17, '6. FG_TRI Fachada no angulo do observador de baixo - Sunset', '2024-09-11', '2024-09-11', '2024-10-23', 'Fachada'),
	(13, 17, '7. FG_TRI Fotomontagem aérea com fachada vista da praia sentido norte - Diurna', '2024-09-11', '2024-09-11', '2024-10-23', 'Fachada'),
	(13, 17, '8. FG_TRI Aérea do lazer de topo - Lazer 1 posterior', '2024-09-11', '2024-09-11', '2024-10-23', 'Imagem externa'),
	(13, 17, '9. FG_TRI Quadra', '2024-09-11', '2024-09-11', '2024-10-23', 'Imagem externa'),
	(13, 17, '10. FG_TRI Pet place', '2024-09-11', '2024-09-11', '2024-10-23', 'Imagem externa'),
	(13, 17, '11. FG_TRI Fire place', '2024-09-11', '2024-09-11', '2024-10-23', 'Imagem externa'),
	(13, 17, '12. FG_TRI Caminhos angulo 1', '2024-09-11', '2024-09-11', '2024-10-23', 'Imagem externa'),
	(13, 17, '13. FG_TRI Caminhos angulo 2', '2024-09-11', '2024-09-11', '2024-10-23', 'Imagem externa'),
	(13, 17, '14. FG_TRI Caminhos angulo 3', '2024-09-11', '2024-09-11', '2024-10-23', 'Imagem externa'),
	(13, 17, '15. FG_TRI Bar - Lazer 1 posterior', '2024-09-11', '2024-09-11', '2024-10-23', 'Imagem externa'),
	(13, 17, '16. FG_TRI Piscina ângulo 1 - Lazer 1 frente mar', '2024-09-11', '2024-09-11', '2024-10-23', 'Imagem externa'),
	(13, 17, '17. FG_TRI Piscina ângulo 2 - Lazer 1 frente mar', '2024-09-11', '2024-09-11', '2024-10-23', 'Imagem externa'),
	(13, 17, '18. FG_TRI Piscina ângulo 3 - Lazer 1 frente mar', '2024-09-11', '2024-09-11', '2024-10-23', 'Imagem externa'),
	(13, 17, '19. FG_TRI Estar da piscina - Lazer 1 frente mar', '2024-09-11', '2024-09-11', '2024-10-23', 'Imagem externa'),
	(13, 17, '20. FG_TRI Estar da piscina - Lazer 1 frente mar', '2024-09-11', '2024-09-11', '2024-10-23', 'Imagem externa'),
	(13, 17, '21. FG_TRI Bar da piscina - Lazer 1 frente mar', '2024-09-11', '2024-09-11', '2024-10-23', 'Imagem externa'),
	(13, 17, '22. FG_TRI Piscina ângulo 1 - Lazer 2', '2024-09-11', '2024-09-11', '2024-10-23', 'Imagem externa'),
	(13, 17, '23. FG_TRI Piscina ângulo 2 - Lazer 2', '2024-09-11', '2024-09-11', '2024-10-23', 'Imagem externa'),
	(13, 17, '24. FG_TRI Estar da piscina ângulo 1 - Lazer 2', '2024-09-11', '2024-09-11', '2024-10-23', 'Imagem externa'),
	(13, 17, '25. FG_TRI Estar da piscina ângulo 2 - Lazer 2', '2024-09-11', '2024-09-11', '2024-10-23', 'Imagem externa'),
	(13, 17, '26. FG_TRI Estar da piscina ângulo 3 - Lazer 2', '2024-09-11', '2024-09-11', '2024-10-23', 'Imagem externa'),
	(13, 17, '27. FG_TRI Estar da piscina ângulo 4 - Lazer 2', '2024-09-11', '2024-09-11', '2024-10-23', 'Imagem externa');

	    RETURN 1;
    END//
    
    alter table imagens_cliente_obra add column tipo_imagem VARCHAR(50);
    alter table imagens_cliente_obra add column status_id INT;
    ALTER TABLE imagens_cliente_obra
ADD CONSTRAINT status_imagem
FOREIGN KEY (status_id)
REFERENCES status_imagem (idstatus);
    
    
    
    
    
select improov.inserir_colaborador();
select improov.inserir_clientes();
select improov.inserir_obras();
select improov.inserir_imagens();
select improov.inserir_status();

SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
