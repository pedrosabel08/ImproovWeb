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
	(4, 4, '20. MSA_SQU Planta humanizada do pavimento tipo', '2024-09-07', '2024-09-07', '2024-09-02'),
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
	(3, 3, '9. PHA_NET Boulevard 2', '2024-06-07', '2024-06-07', '2024-08-01'),
	(3, 3, '10. PHA_NET Boulevard Noturno', '2024-06-07', '2024-06-07', '2024-08-01'),
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
	(3, 3, '39. PHA_NET Living apto Q', '2024-06-07', '2024-06-07', '2024-08-01');

	    RETURN 1;
    END//
    

select improov.inserir_colaborador();
select improov.inserir_clientes();
select improov.inserir_obras();
select improov.inserir_imagens();

SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
