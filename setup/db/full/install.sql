 CREATE TABLE IF NOT EXISTS dashboard (
  id int unsigned AUTO_INCREMENT NOT NULL,
  name varchar (255) NOT NULL,
  PRIMARY KEY (`id`)
);

CREATE TABLE widget (
  id int unsigned AUTO_INCREMENT NOT NULL,
  dashboard_id int unsigned,
  xCord int unsigned,
  yCord int unsigned,
  width int unsigned,
  height int unsigned,
  pluginTypeId varchar(40),
  PRIMARY KEY (id)
)


ALTER TABLE widget ADD FOREIGN KEY (dashboard_id) REFERENCES dashboard(id);