

CREATE TABLE users(
  login VARCHAR(255) NOT NULL,
  salt VARCHAR(32) DEFAULT NULL,
  PRIMARY KEY (login)
)
ENGINE = INNODB
CHARACTER SET latin1
COLLATE latin1_swedish_ci;



CREATE TABLE blocks(
  `hash` VARCHAR(6) NOT NULL,
  block VARCHAR(10) NOT NULL,
  PRIMARY KEY (`hash`)
)
ENGINE = INNODB
CHARACTER SET latin1
COLLATE latin1_swedish_ci;



CREATE TABLE passwords (
  `hash` VARCHAR(40) NOT NULL,
  PRIMARY KEY (`hash`)
)
ENGINE = INNODB
CHARACTER SET latin1
COLLATE latin1_swedish_ci;