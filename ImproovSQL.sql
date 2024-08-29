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
CREATE TABLE imagens_cliente_obra (
    idimagens_cliente_obra INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    obra_id INT NOT NULL,
    imagem_nome VARCHAR(255) NOT NULL,
    status VARCHAR(50),
    prazo DATE,
    FOREIGN KEY (cliente_id) REFERENCES cliente(idcliente),
    FOREIGN KEY (obra_id) REFERENCES obra(idobra)
);

-- -----------------------------------------------------
-- Table `improov`.`funcao_imagem`
-- -----------------------------------------------------
CREATE TABLE funcao_imagem (
    idfuncao_imagem INT AUTO_INCREMENT PRIMARY KEY,
    imagem_id INT NOT NULL,
    colaborador_id INT NOT NULL,
    funcao_id INT NOT NULL,
    FOREIGN KEY (imagem_id) REFERENCES imagens_cliente_obra(idimagens_cliente_obra),
    FOREIGN KEY (colaborador_id) REFERENCES colaborador(idcolaborador),
    FOREIGN KEY (funcao_id) REFERENCES funcao(idfuncao)
);

INSERT INTO funcao (nome_funcao) VALUES ('Caderno');
INSERT INTO funcao (nome_funcao) VALUES ('Modelagem');
INSERT INTO funcao (nome_funcao) VALUES ('Composição');
INSERT INTO funcao (nome_funcao) VALUES ('Finalização');
INSERT INTO funcao (nome_funcao) VALUES ('Pós-produção');
INSERT INTO funcao (nome_funcao) VALUES ('Planta Humanizada');

INSERT INTO colaborador (nome_colaborador) VALUES ('João Silva');
INSERT INTO colaborador (nome_colaborador) VALUES ('Maria Oliveira');
INSERT INTO colaborador (nome_colaborador) VALUES ('Pedro Santos');
INSERT INTO cliente (nome_cliente) VALUES ('Cliente A');
INSERT INTO cliente (nome_cliente) VALUES ('Cliente B');
INSERT INTO obra (nome_obra) VALUES ('Obra 1');
INSERT INTO obra (nome_obra) VALUES ('Obra 2');
INSERT INTO imagens_cliente_obra (cliente_id, obra_id, imagem_nome, status, prazo)
VALUES (1, 1, 'imagem1.jpg', 'Em andamento', '2024-09-15');

INSERT INTO imagens_cliente_obra (cliente_id, obra_id, imagem_nome, status, prazo)
VALUES (2, 2, 'imagem2.jpg', 'Finalizado', '2024-08-30');
INSERT INTO funcao_imagem (imagem_id, colaborador_id, funcao_id)
VALUES (1, 1, 1);  -- Modelador para Imagem 1

INSERT INTO funcao_imagem (imagem_id, colaborador_id, funcao_id)
VALUES (1, 2, 2);  -- Compositor para Imagem 1

INSERT INTO funcao_imagem (imagem_id, colaborador_id, funcao_id)
VALUES (1, 3, 3);  -- Finalizador para Imagem 1

INSERT INTO funcao_imagem (imagem_id, colaborador_id, funcao_id)
VALUES (2, 2, 1);  -- Modelador para Imagem 2

SELECT i.idimagens_cliente_obra, c.nome_cliente, o.nome_obra, i.imagem_nome, i.status, i.prazo
FROM imagens_cliente_obra i
JOIN cliente c ON i.cliente_id = c.idcliente
JOIN obra o ON i.obra_id = o.idobra;









SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
