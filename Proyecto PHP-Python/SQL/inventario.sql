-- Schema e inserts de ejemplo para SQLite
PRAGMA foreign_keys = ON;

DROP TABLE IF EXISTS inventario;
CREATE TABLE IF NOT EXISTS inventario (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  nombre TEXT NOT NULL,
  responsable TEXT NOT NULL,
  estado TEXT NOT NULL,
  fecha TEXT NOT NULL
);

INSERT INTO inventario (nombre, responsable, estado, fecha) VALUES
('Balón de fútbol','Juan Pérez','Disponible','2025-08-12'),
('Uniforme','María Gómez','En uso','2025-08-15'),
('Botines','Carlos Ruiz','Disponible','2025-09-01'),
('Conos','Ana Torres','En reparación','2025-09-05'),
('Chalecos','Luis Fernández','Disponible','2025-09-10'),
('Cronómetro','Sofía López','En uso','2025-09-12'),
('Bebidas','Diego Castro','Disponible','2025-09-18'),
('Guantes','Laura Méndez','En uso','2025-09-20'),
('Pechera','Juan Pérez','Disponible','2025-09-22'),
('Aros','María Gómez','En reparación','2025-09-25'),
('Colchoneta','Carlos Ruiz','Disponible','2025-09-27'),
('Balón de baloncesto','Ana Torres','En uso','2025-09-30'),
('Red portátil','Luis Fernández','Disponible','2025-10-02'),
('Bomba de aire','Sofía López','Disponible','2025-10-05'),
('Cinta métrica','Diego Castro','En reparación','2025-10-08'),
('Cámara','Laura Méndez','Disponible','2025-10-12'),
('Trípode','Juan Pérez','En uso','2025-10-15'),
('Tableta','María Gómez','Disponible','2025-10-18'),
('Silbato','Carlos Ruiz','Disponible','2025-10-20'),
('Botiquín','Ana Torres','En uso','2025-10-22'),
('Balón de voleibol','Luis Fernández','Disponible','2025-10-24'),
('Red de voleibol','Sofía López','En reparación','2025-10-26'),
('Cinta kinesio','Diego Castro','Disponible','2025-10-28'),
('Toalla deportiva','Laura Méndez','Disponible','2025-10-30');