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
    FOREIGN KEY (cliente_id) REFERENCES cliente(idcliente),
    FOREIGN KEY (obra_id) REFERENCES obra(idobra)
);

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

INSERT INTO funcao (nome_funcao) VALUES ('Caderno');
INSERT INTO funcao (nome_funcao) VALUES ('Modelagem');
INSERT INTO funcao (nome_funcao) VALUES ('Composição');
INSERT INTO funcao (nome_funcao) VALUES ('Finalização');
INSERT INTO funcao (nome_funcao) VALUES ('Pós-produção');
INSERT INTO funcao (nome_funcao) VALUES ('Planta Humanizada');

INSERT INTO colaborador(nome_colaborador) VALUES ('Pedro');
INSERT INTO colaborador(nome_colaborador) VALUES ('Bruna');
INSERT INTO colaborador(nome_colaborador) VALUES ('André');
INSERT INTO colaborador(nome_colaborador) VALUES ('Anderson');

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

CREATE FUNCTION inserir_imagens() RETURNS int
DETERMINISTIC
BEGIN

INSERT INTO imagens_cliente_obra (cliente_id, obra_id, imagem_nome, recebimento_arquivos, data_inicio, prazo) values
	(2, 2, '1. AYA_CAS Portaria', '2024-08-21', '2024-08-21', '2024-09-27'),
	(2, 2, '2. AYA_CAS Boulevard entre casas', '2024-08-21', '2024-08-21', '2024-09-27'),
	(2, 2, '3. AYA_CAS Fotomontagem aérea', '2024-08-21', '2024-08-21', '2024-09-27'),
	(2, 2, '4. AYA_CAS Implantação', '2024-08-21', '2024-08-21', '2024-09-27'),
	(2, 2, '5. AYA_CAS Casa fundos', '2024-08-21', '2024-08-21', '2024-09-27'),
	(2, 2, '6. AYA_CAS Living olhando para fora ângulo 1', '2024-08-21', '2024-08-21', '2024-09-27'),
	(2, 2, '7. AYA_CAS Living olhando para fora ângulo 2', '2024-08-21', '2024-08-21', '2024-09-27'),
	(2, 2, '8. AYA_CAS Fachada aérea', '2024-08-21', '2024-08-21', '2024-09-27'),
	(2, 2, '9. AYA_CAS Suíte casa nova olhando para copa das árvores', '2024-08-21', '2024-08-21', '2024-09-27'),
	(3, 3, '1. MSA_SQU Fotomontagem aérea com inserção do empreendimento em terreno real ângulo 1', '2024-09-07', '2024-09-07', '2024-09-02'),
	(3, 3, '2. MSA_SQU Fotomontagem aérea com inserção do empreendimento em terreno real ângulo 2', '2024-09-07', '2024-09-07', '2024-09-02'),
	(3, 3, '3. MSA_SQU Fachada no ângulo do observador', '2024-09-07', '2024-09-07', '2024-09-02'),
	(3, 3, '4. MSA_SQU Embasamento mostrando área comercial', '2024-09-07', '2024-09-07', '2024-09-02'),
	(3, 3, '5. MSA_SQU Hall de entrada', '2024-09-07', '2024-09-07', '2024-09-02'),
	(3, 3, '6. MSA_SQU Salão de festas a definir (1 ou 2) Qual ficar maior e melhor', '2024-09-07', '2024-09-07', '2024-09-02'),
	(3, 3, '7. MSA_SQU Sala de jogos / PUB', '2024-09-07', '2024-09-07', '2024-09-02'),
	(3, 3, '8. MSA_SQU Academia', '2024-09-07', '2024-09-07', '2024-09-02'),
	(3, 3, '9. MSA_SQU Quiosque Piscina', '2024-09-07', '2024-09-07', '2024-09-02'),
	(1, 1, '10. MSA_SQU Brinquedoteca', '2024-09-07', '2024-09-07', '2024-09-02'),
	(3, 3, '11. MSA_SQU Piscina', '2024-09-07', '2024-09-07', '2024-09-02'),
	(3, 3, '12. MSA_SQU Lounge Ofurô', '2024-09-07', '2024-09-07', '2024-09-02'),
	(3, 3, '13. MSA_SQU Living do apartamento tipo (unidade à definir)', '2024-09-07', '2024-09-07', '2024-09-02'),
	(3, 3, '14. MSA_SQU Suíte do apartamento tipo (unidade à definir)', '2024-09-07', '2024-09-07', '2024-09-02'),
	(3, 3, '15. MSA_SQU Sacada com vista real do apartamento face rua Dorvalino', '2024-09-07', '2024-09-07', '2024-09-02'),
	(3, 3, '16. MSA_SQU Sacada com vista real do apartamento face rua Dorvalino', '2024-09-07', '2024-09-07', '2024-09-02'),
	(3, 3, '17. MSA_SQU Sacada com vista real do apartamento face rua Dorvalino', '2024-09-07', '2024-09-07', '2024-09-02'),
	(3, 3, '18. MSA_SQU Planta humanizada implantação geral mostrando o pavimento térreo', '2024-09-07', '2024-09-07', '2024-09-02'),
	(3, 3, '19. MSA_SQU Planta humanizada do pavimento lazer', '2024-09-07', '2024-09-07', '2024-09-02'),
	(3, 3, '20. MSA_SQU Planta humanizada do pavimento tipo', '2024-09-07', '2024-09-07', '2024-09-02'),
	(4, 4, '1. RDO_VIL Fachada diurna', '2024-07-12', '2024-07-12', '2024-08-28'),
	(4, 4, '2. RDO_VIL Fachada noturna', '2024-07-12', '2024-07-12', '2024-08-28'),
	(4, 4, '3. RDO_VIL Fachada com aproximação do pórtico', '2024-07-12', '2024-07-12', '2024-08-28'),
	(4, 4, '4. RDO_VIL Imagem de drone com a fachada aplicada (temos essa imagem).', '2024-07-12', '2024-07-12', '2024-08-28'),
	(4, 4, '5. RDO_VIL Hall de entrada', '2024-07-12', '2024-07-12', '2024-08-28'),
	(4, 4, '6. RDO_VIL Business', '2024-07-12', '2024-07-12', '2024-08-28'),
	(4, 4, '7. RDO_VIL Salão de festas', '2024-07-12', '2024-07-12', '2024-08-28'),
	(4, 4, '8. RDO_VIL Gourmet', '2024-07-12', '2024-07-12', '2024-08-28'),
	(4, 4, '9. RDO_VIL Varanda', '2024-07-12', '2024-07-12', '2024-08-28'),
	(4, 4, '10. RDO_VIL Pub', '2024-07-12', '2024-07-12', '2024-08-28'),
	(4, 4, '11. RDO_VIL Sala de jogos', '2024-07-12', '2024-07-12', '2024-08-28'),
	(4, 4, '12. RDO_VIL Teens', '2024-07-12', '2024-07-12', '2024-08-28'),
	(4, 4, '13. RDO_VIL Kids', '2024-07-12', '2024-07-12', '2024-08-28'),
	(4, 4, '14. RDO_VIL Play+Petplace (externo)', '2024-07-12', '2024-07-12', '2024-08-28'),
	(4, 4, '15. RDO_VIL Piscina aquecida', '2024-07-12', '2024-07-12', '2024-08-28'),
	(4, 4, '16. RDO_VIL Spa', '2024-07-12', '2024-07-12', '2024-08-28'),
	(4, 4, '17. RDO_VIL Academia', '2024-07-12', '2024-07-12', '2024-08-28'),
	(4, 4, '18. RDO_VIL Market', '2024-07-12', '2024-07-12', '2024-08-28'),
	(4, 4, '19. RDO_VIL Quiosque+quadra', '2024-07-12', '2024-07-12', '2024-08-28'),
	(4, 4, '20. RDO_VIL Fireplace + horta', '2024-07-12', '2024-07-12', '2024-08-28'),
	(4, 4, '21. RDO_VIL Car Care/Vagas verdes', '2024-07-12', '2024-07-12', '2024-08-28'),
	(4, 4, '22. RDO_VIL Bicicletário', '2024-07-12', '2024-07-12', '2024-08-28'),
	(4, 4, '23. RDO_VIL Living de um apartamento', '2024-07-12', '2024-07-12', '2024-08-28'),
	(4, 4, '24. RDO_VIL Planta humanizada do pavimento lazer', '2024-07-12', '2024-07-12', '2024-08-28');
    
	
    
	    RETURN 1;
    END//
    
select improov.inserir_clientes();
select improov.inserir_obras();
select improov.inserir_imagens();

SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
