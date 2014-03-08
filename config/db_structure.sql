SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='TRADITIONAL';

CREATE SCHEMA IF NOT EXISTS `mydb` DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci ;
USE `mydb` ;

-- -----------------------------------------------------
-- Table `__prefix__entity_type`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `__prefix__entity_type` ;

CREATE  TABLE IF NOT EXISTS `__prefix__entity_type` (
  `id` INT NOT NULL AUTO_INCREMENT ,
  `name` VARCHAR(256) NOT NULL ,
  PRIMARY KEY (`id`) ,
  UNIQUE INDEX `entity_name_idx` (`name` ASC) ,
  UNIQUE INDEX `entity_type_name_idx` (`name` ASC) )
ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `__prefix__entity_dimension_i18n`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `__prefix__entity_dimension_i18n` ;

CREATE  TABLE IF NOT EXISTS `__prefix__entity_dimension_i18n` (
  `id` INT NOT NULL AUTO_INCREMENT ,
  `entity_type_id` INT NOT NULL ,
  `language_id` INT NOT NULL ,
  `name` VARCHAR(256) NOT NULL ,
  PRIMARY KEY (`id`) ,
  UNIQUE INDEX `dimension_language_idx` (`entity_type_id` ASC, `language_id` ASC, `name` ASC) ,
  FULLTEXT INDEX `dimension_fulltext_idx` (`name` ASC) ,
  INDEX `dimension_idx` (`name` ASC) )
ENGINE = MyISAM;


-- -----------------------------------------------------
-- Table `__prefix__entity`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `__prefix__entity` ;

CREATE  TABLE IF NOT EXISTS `__prefix__entity` (
  `id` INT NOT NULL AUTO_INCREMENT COMMENT '  ' ,
  `entity_type_id` INT NOT NULL ,
  `foreign_identifier` VARCHAR(256) NOT NULL ,
  PRIMARY KEY (`id`) ,
  INDEX `fk_entity_1` (`entity_type_id` ASC) ,
  UNIQUE INDEX `entity_identifier_idx` (`entity_type_id` ASC, `foreign_identifier` ASC) ,
  CONSTRAINT `fk_entity_1`
    FOREIGN KEY (`entity_type_id` )
    REFERENCES `__prefix__entity_type` (`id` )
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `__prefix__entity_i18n`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `__prefix__entity_i18n` ;

CREATE  TABLE IF NOT EXISTS `__prefix__entity_i18n` (
  `id` INT NOT NULL AUTO_INCREMENT ,
  `entity_id` INT NOT NULL ,
  `language_id` INT NOT NULL ,
  `name` VARCHAR(256) NOT NULL ,
  `weight` FLOAT NOT NULL ,
  PRIMARY KEY (`id`) ,
  FULLTEXT INDEX `entity_i18n_name_fulltext_idx` (`name` ASC) ,
  INDEX `entity_i18n_name_idx` (`name` ASC) ,
  UNIQUE INDEX `entity_i18n_name_language_idx` (`entity_id` ASC, `language_id` ASC) ,
  INDEX `entity_weight_idx` (`weight` ASC) )
ENGINE = MyISAM;


-- -----------------------------------------------------
-- Table `__prefix__entity_characteristic_i18n`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `__prefix__entity_characteristic_i18n` ;

CREATE  TABLE IF NOT EXISTS `__prefix__entity_characteristic_i18n` (
  `id` INT NOT NULL AUTO_INCREMENT ,
  `entity_id` INT NOT NULL ,
  `entity_dimension_id` INT NOT NULL ,
  `language_id` INT NOT NULL ,
  `value` VARCHAR(256) NOT NULL ,
  `weight` FLOAT NOT NULL ,
  PRIMARY KEY (`id`) ,
  FULLTEXT INDEX `entity_characteristic_value_fulltext_idx` (`value` ASC) ,
  INDEX `entity_characteristic_value_idx` (`value` ASC) ,
  INDEX `entity_id_idx` (`entity_id` ASC) ,
  INDEX `entity_dimension_idx` (`entity_dimension_id` ASC, `language_id` ASC) ,
  INDEX `entity_characteristic_weight_idx` (`weight` ASC) )
ENGINE = MyISAM;



SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;