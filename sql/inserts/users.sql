-- Inserts pour la table users
-- Utilisateurs avec leurs codes d'activation uniques

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

INSERT INTO `users` (group_id, firstname, lastname, username, email, activation_code) VALUES
-- Groupe 1: Colonnel Moutarde (ADV Compta)
(1, 'Omniya', 'OULED LOUNIS', 'oouledlounis', 'olounis@exomind.fr', 'A7K9X2'),
(1, 'Flavien', 'SOUTERA', 'fsoutera', 'fsoutera@exomind.fr', 'B3M5Y8'),

-- Groupe 2: Mademoiselle Rose (Comm Market)
(2, 'Victorine', 'FILLE', 'vfille', 'vfille@exomind.fr', 'C8N4Z1'),
(2, 'Margaux', 'BEAUMONT', 'mbeaumont', 'mbeaumont@exomind.fr', 'D2P6W9'),

-- Groupe 3: Madame Leblanc (Recruteurs)
(3, 'Mélanie', 'GUNST', 'mgunst', 'mgunst@exomind.fr', 'E5Q7V3'),
(3, 'Oscar', 'LEHIDEUX', 'olehideux', 'olehideux@exomind.fr', 'F9R1X4'),
(3, 'Kissima', 'SOUMARE', 'ksoumare', 'ksoumare@exomind.fr', 'G4S8Y6'),
(3, 'Nomenihavo', 'RAPHAELSON', 'nraphaelson', 'nraphaelson@exomind.fr', 'H1T3Z7'),

-- Groupe 4: Madame Pervenche (Business)
(4, 'Mathieu', 'DANTO', 'mdanto', 'mdanto@exomind.fr', 'J6U9A2'),
(4, 'Alexis', 'GUENOT', 'aguenot', 'aguenot@exomind.fr', 'K3V2B8'),
(4, 'Andrea', 'THIBAUD', 'athibaud', 'athibaud@exomind.fr', 'L7W5C1'),
(4, 'Aurélien', 'GARDIN', 'agardin', 'agardin@exomind.fr', 'M2X8D4'),
(4, 'Mathis', 'BOCCHINO', 'mbocchino', 'mbocchino@exomind.fr', 'N9Y1E6'),
(4, 'Manon', 'GLAENZER', 'mglaenzer', 'mglaenzer@exomind.fr', 'P4Z3F9'),
(4, 'Dalvince', 'BOYER', 'dboyer', 'dboyer@exomind.fr', 'Q8A6G2'),
(4, 'Victor', 'ADROIT', 'vadroit', 'vadroit@exomind.fr', 'R1B9H5'),

-- Groupe 5: Révérend Olive (CDS)
(5, 'Christophe', 'QUENTEL', 'cquentel', 'cquentel@exomind.fr', 'S5C2J8'),
(5, 'Élora', 'DELPIERRE', 'edelpierre', 'edelpierre@exomind.fr', 'T9D6K1'),
(5, 'Pierre', 'CILLUFFO', 'pcilluffo', 'pcilluffo@exomind.fr', 'U3E8L4'),
(5, 'Matthias', 'PETIT', 'mpetit', 'mpetit@exomind.fr', 'V7F1M9'),
(5, 'Dorian', 'CANONNE', 'dcanonne', 'dcanonne@exomind.fr', 'W2G5N3'),
(5, 'Kévin', 'FORTES', 'kfortes', 'kfortes@exomind.fr', 'X6H9P7'),
(5, 'Jordan', 'OLLIVIER', 'jollivier', 'jollivier@exomind.fr', 'Y1J3Q2'),
(5, 'Killian', 'THÉOPHILE', 'ktheophile', 'ktheophile@exomind.fr', 'Z4K7R8'),
(5, 'Rémi', 'BALBOUS', 'rbalbous', 'rbalbous@exomind.fr', 'A8L2S5'),
(5, 'Safwen', 'TRABELSI', 'strabelsi', 'strabelsi@exomind.fr', 'B3M6T9'),
(5, 'Sega', 'DIARRA', 'sdiarra', 'sdiarra@exomind.fr', 'C7N1U4'),
(5, 'Khaled', 'EL ABED', 'kelabed', 'kelabed@exomind.fr', 'D2P5V8'),
(5, 'Félix', 'HAMILTON', 'fhamilton', 'fhamilton@exomind.fr', 'E6Q9W1'),
(5, 'Fredy', 'MBOVING', 'fmboving', 'fmboving@exomind.fr', 'F1R3X7'),
(5, 'Albert', 'KOUASSI KOUAKOU', 'akouakou', 'akouakou@exomind.fr', 'G5S8Y2'),
(5, 'Julien', 'N\'DRI', 'jndri', 'jndri@exomind.fr', 'H9T2Z6'),
(5, 'Valentin', 'LIMAGNE', 'vlimagne', 'vlimagne@exomind.fr', 'J4U7A3'),

-- Groupe 6: Professeur Violet (Consultants)
(6, 'Alizée', 'LE ROUX', 'aleroux', 'aleroux@exomind.fr', 'K8V1B9'),
(6, 'Amin', 'ATITALLAH', 'aatitallah', 'aatitallah@exomind.fr', 'L3W6C4'),
(6, 'Atef', 'ZAAFOURI', 'azaafouri', 'azaafouri@exomind.fr', 'M7X2D8'),
(6, 'David', 'ROBERT', 'drobert', 'drobert@exomind.fr', 'N2Y5E1'),
(6, 'Dorian', 'BELHAJ', 'dbelhaj', 'dbelhaj@exomind.fr', 'P6Z9F3'),
(6, 'Dustin', 'KROEGER', 'dkroeger', 'dkroeger@exomind.fr', 'Q1A3G7'),
(6, 'Evie', 'BONMARCHAND', 'ebonmarchand', 'ebonmarchand@exomind.fr', 'R5B8H2'),
(6, 'Icaro', 'ROCHA', 'irocha', 'irocha@exomind.fr', 'S9C2J6'),
(6, 'Laurianne', 'LEBRETON', 'llebreton', 'llebreton@exomind.fr', 'T4D7K1'),
(6, 'Loris', 'TECHER', 'ltecher', 'ltecher@exomind.fr', 'U8E1L5'),
(6, 'Lucas', 'WILMART', 'lwilmart', 'lwilmart@exomind.fr', 'V3F6M9'),
(6, 'Mathieu', 'DJELLALI', 'mdjellali', 'mdjellali@exomind.fr', 'W7G2N4'),
(6, 'Paul', 'DICKERSON', 'pdickerson', 'pdickerson@exomind.fr', 'X2H5P8'),
(6, 'Taysir', 'BEN HAMED', 'tbenhamed', 'tbenhamed@exomind.fr', 'Y6J9Q3'),
(6, 'Thomas', 'DELAJON', 'tdelajon', 'tdelajon@exomind.fr', 'Z1K4R7'),
(6, 'Tsiori', 'TIANJANAHARY', 'ttianjanahary', 'ttianjanahary@exomind.fr', 'A5L8S2'),
(6, 'Alice', 'TAMME', 'atamme', 'atamme@exomind.fr', 'B9M3T6'),
(6, 'Juliette', 'HALBOT', 'jhalbot', 'jhalbot@exomind.fr', 'C4N7U1'),
(6, 'Valentin', 'CHABERT', 'vchabert', 'vchabert@exomind.fr', 'D8P2V5'),
(6, 'Yann', 'LOJEWSKI', 'ylojewski', 'ylojewski@exomind.fr', 'E3Q6W9'),
(6, 'Abdelilah', 'NEQROUZ', 'aneqrouz', 'aneqrouz@exomind.fr', 'F7R1X4'),
(6, 'Éric', 'BAPTISTAL', 'ebaptistal', 'ebaptistal@exomind.fr', 'G2S5Y8'),
(6, 'Frédéric', 'VANNIER', 'fvannier', 'fvannier@exomind.fr', 'H6T9Z3'),
(6, 'Guillaume', 'MOREL', 'gmorel', 'gmorel@exomind.fr', 'J1U4A7'),
(6, 'Léo', 'POUPET', 'lpoupet', 'lpoupet@exomind.fr', 'K5V8B2'),
(6, 'Philippe', 'DOUSSET', 'pdousset', 'pdousset@exomind.fr', 'L9W3C6'),
(6, 'Alexandre', 'MERROUR', 'amerrour', 'alexandre@tak.fr', 'M4X7D1'),
(6, 'Alice', 'NICOLAS', 'anicolas', 'alice@tak.fr', 'N8Y2E5'),
(6, 'Amina', 'BOUDJEMLINE', 'aboudjemline', 'amina@tak.fr', 'P3Z6F9'),
(6, 'Camille', 'URREA', 'currea', 'camille@tak.fr', 'Q7A1G4'),
(6, 'Damien', 'DURAND', 'ddurand', 'damien@tak.fr', 'R2B5H8'),
(6, 'Emmanuel', 'MARIS', 'emaris', 'emmanuel@tak.fr', 'S6C9J3'),
(6, 'Henri', 'LACOSTE', 'hlacoste', 'henri@tak.fr', 'T1D4K7'),
(6, 'Jonathan', 'LAMAC', 'jlamac', 'jonathan@tak.fr', 'U5E8L2'),
(6, 'Lola', 'BOURDENET', 'lbourdenet', 'lola@tak.fr', 'V9F3M6'),
(6, 'Maïder', 'ESPINOSA', 'mespinosa', 'maider@tak.fr', 'W4G7N1'),
(6, 'Roxanne', 'SITBON', 'rsitbon', 'roxanne@tak.fr', 'X8H2P5'),
(6, 'Théo', 'DEORSOLA', 'tdeorsola', 'theo@tak.fr', 'Y3J6Q9'),
(6, 'Xavier', 'LEVY', 'xlevy', 'xavier@tak.fr', 'Z7K1R4'),
(6, 'Antoine', 'DANIBO', 'adanibo', 'adanibo@exomind.fr', 'A2L5S8'),
(6, 'Clémence', 'LAURENT', 'claurent', 'claurent@exomind.fr', 'B6M9T3'),
(6, 'Johann', 'PETZOLD', 'jpetzold', 'jpetzold@exomind.fr', 'C1N4U7'),
(6, 'Michael', 'BARUCH', 'mbaruch', 'mbaruch@exomind.fr', 'D5P8V2'),
(6, 'Thileepan', 'UMAPATHIPILLAI', 'tumapathipillai', 'thileepan@exomind.fr', 'E9Q3W6')
ON DUPLICATE KEY UPDATE 
    firstname = VALUES(firstname),
    lastname = VALUES(lastname),
    username = VALUES(username),
    email = VALUES(email),
    activation_code = VALUES(activation_code),
    updated_at = CURRENT_TIMESTAMP;