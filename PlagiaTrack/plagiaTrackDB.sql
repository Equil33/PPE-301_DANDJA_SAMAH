-- Script SQL pour la création de la base de données PlagiaTrack
-- Base de données : plagiaTrackDB

DROP DATABASE IF EXISTS plagiaTrackDB;
CREATE DATABASE plagiaTrackDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE plagiaTrackDB;

-- Table des rôles
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(50) NOT NULL UNIQUE,
    description TEXT
) ENGINE=InnoDB;

-- Insertion des rôles de base
INSERT INTO roles (nom, description) VALUES
('super_admin', 'Super administrateur unique avec tous les droits'),
('administrateur', 'Administrateur avec gestion complète'),
('professeur', 'Professeur avec droits de soumission et visualisation'),
('etudiant', 'Étudiant avec droits de soumission et visualisation limitée');

-- Table des permissions
CREATE TABLE permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL UNIQUE,
    description TEXT
) ENGINE=InnoDB;

-- Table de liaison roles-permissions
CREATE TABLE role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Table des utilisateurs
CREATE TABLE utilisateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    mot_de_passe_hash VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    statut ENUM('actif', 'inactif', 'sanctionne') DEFAULT 'actif',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_modification DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    est_super_admin BOOLEAN DEFAULT FALSE,
    CONSTRAINT fk_role_utilisateur FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

-- Table des sanctions
CREATE TABLE sanctions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL,
    type_sanction VARCHAR(100) NOT NULL,
    raison TEXT NOT NULL,
    date_sanction DATETIME DEFAULT CURRENT_TIMESTAMP,
    statut ENUM('active', 'levee') DEFAULT 'active',
    CONSTRAINT fk_sanction_utilisateur FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Table des documents
CREATE TABLE documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL,
    nom_fichier VARCHAR(255) NOT NULL,
    chemin VARCHAR(500) NOT NULL,
    type_fichier ENUM('pdf', 'docx', 'txt') NOT NULL,
    date_soumission DATETIME DEFAULT CURRENT_TIMESTAMP,
    statut ENUM('en_attente', 'analyse', 'valide', 'supprime') DEFAULT 'en_attente',
    est_enregistre BOOLEAN DEFAULT FALSE,
    CONSTRAINT fk_document_utilisateur FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Table des groupes de documents
CREATE TABLE groupes_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255) NOT NULL,
    description TEXT,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    createur_id INT DEFAULT NULL,
    CONSTRAINT fk_groupe_createur FOREIGN KEY (createur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Table d'association documents-groupes
CREATE TABLE groupe_document_assoc (
    groupe_id INT NOT NULL,
    document_id INT NOT NULL,
    PRIMARY KEY (groupe_id, document_id),
    CONSTRAINT fk_assoc_groupe FOREIGN KEY (groupe_id) REFERENCES groupes_documents(id) ON DELETE CASCADE,
    CONSTRAINT fk_assoc_document FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Table des rapports de plagiat
CREATE TABLE rapports_plagiat (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    pourcentage_plagiat DECIMAL(5,2) NOT NULL,
    chemin_rapport VARCHAR(500) NOT NULL,
    date_analyse DATETIME DEFAULT CURRENT_TIMESTAMP,
    statut ENUM('actif', 'archive', 'supprime') DEFAULT 'actif',
    CONSTRAINT fk_rapport_document FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Table des notifications
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL,
    type_notification VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    statut ENUM('non_lu', 'lu') DEFAULT 'non_lu',
    date_envoi DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notification_utilisateur FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Table de journalisation des actions
CREATE TABLE journal_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT DEFAULT NULL,
    action VARCHAR(255) NOT NULL,
    cible VARCHAR(255),
    date_action DATETIME DEFAULT CURRENT_TIMESTAMP,
    details TEXT,
    CONSTRAINT fk_journal_utilisateur FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Table des paramètres d'analyse personnalisés
CREATE TABLE parametres_analyse (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL,
    parametres_json TEXT NOT NULL,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_parametres_utilisateur FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Table pour gérer la création du super administrateur unique
CREATE TABLE super_admin_creation (
    id INT PRIMARY KEY CHECK (id = 1),
    est_cree BOOLEAN NOT NULL DEFAULT FALSE
) ENGINE=InnoDB;

-- Initialisation de la table super_admin_creation
INSERT INTO super_admin_creation (id, est_cree) VALUES (1, FALSE);

-- Index pour optimiser les recherches
CREATE INDEX idx_utilisateur_role ON utilisateurs(role_id);
CREATE INDEX idx_document_utilisateur ON documents(utilisateur_id);
CREATE INDEX idx_rapport_document ON rapports_plagiat(document_id);
CREATE INDEX idx_notification_utilisateur ON notifications(utilisateur_id);
