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

INSERT INTO funcao_imagem (imagem_id, colaborador_id, funcao_id, prazo, status) values (1, 1, 1, '2024-03-09', 'Finalizado');
INSERT INTO funcao_imagem (imagem_id, colaborador_id, funcao_id, prazo, status) values (1, 2, 2, '2024-03-09', 'Finalizado');
INSERT INTO funcao_imagem (imagem_id, colaborador_id, funcao_id, prazo, status) values (1, 3, 3, '2024-03-09', 'Finalizado');
INSERT INTO funcao_imagem (imagem_id, colaborador_id, funcao_id, prazo, status) values (1, 4, 4, '2024-03-09', 'Finalizado');
INSERT INTO funcao_imagem (imagem_id, colaborador_id, funcao_id, prazo, status) values (1, 1, 5, '2024-03-09', 'Finalizado');
INSERT INTO funcao_imagem (imagem_id, colaborador_id, funcao_id, prazo, status) values (1, 2, 6, '2024-03-09', 'Finalizado');

SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
