<?php
    if (!isset($_SESSION)) {
        session_start();
    }
    require_once '../../Configuration/ConfigUtilisee.php';
    set_include_path('../' . LIB . '/PEAR/');
    require_once '../' . LIB . '/PEAR/Crypt/RSA.php';
    require_once '../' . ENV . '/Adaptations/CryptRSA/class/myKey.php';
    require_once '../' . ENV . '/Adaptations/CryptRSA/class/myKeyPair.php';
    require_once '../' . ENV . '/Adaptations/CryptRSA/class/myRSA.php';
    require_once '../../Modeles/Classes/ClassPersonne.php';
    require_once '../../' . CONFIG . '/PostGreSQL.php';
    require_once '../' . ENV . '/Outils/Fct.php';
    
    switch ($_POST['action']) {
        case 'Deconnecter':
            deconnecteAppli(APPLI);
            $data = 'Vous êtes à présent déconnectés !!!';
            die('{success: true, data: "' . $data . '"}');
        break;
        case 'Authentifier':
            // Décryptage RSA personnalisé du mot de passe utilisateur pour authentification
            if (CRYPTAGE) {
                $rsa_obj_perso = new Crypt_myRSA(null, 'BCMath');
                $private_key_perso = new Crypt_RSA_myKey($_SESSION[APPLI]['Securite']['private_module_perso'], $_SESSION[APPLI]['Securite']['private_exp_perso'], 'private', 'BCMath');
                $mot_de_passe = pg_escape_string($rsa_obj_perso->decrypt($_POST['mot_de_passe'], $private_key_perso));// besoin de "pg_escape_string" car valeur maîtrisée par l'utilisateur
                // Regénération de nouvelles clés RSA classiques
                $nbBits = 128;
                $key_pair = new Crypt_RSA_KeyPair($nbBits, 'BCMath');
                $public_key = $key_pair->getPublicKey();
                $private_key = $key_pair->getPrivateKey();
                // Remplacement en session par la clé privée classique
                $_SESSION[APPLI]['Securite']['private_module'] = $private_key->getModulus();
                $_SESSION[APPLI]['Securite']['private_exp'] = $private_key->getExponent();
                // Cryptage RSA classique des variables de session utilisées dans le constructeur de "CnxPgObsOcc" appelé par "Personne::authentifie"
                $rsa_obj = new Crypt_RSA(null, 'BCMath');
                $_SESSION[APPLI]['Connexion']['USER'] = $rsa_obj->encrypt(USER, $public_key);
                $_SESSION[APPLI]['Connexion']['PASSWORD'] = $rsa_obj->encrypt(PASSWORD, $public_key);
            }
            else {
                $_SESSION[APPLI]['Connexion']['USER'] = USER;
                $_SESSION[APPLI]['Connexion']['PASSWORD'] = PASSWORD;
                $mot_de_passe = pg_escape_string($_POST['mot_de_passe']);
            }
            // Authentification utilisateur avec passage du mot de passe crypté MD5 (protection SGDB)
            $id = Personne::authentifie(pg_escape_string($_POST['email']), md5($mot_de_passe));// besoin de "pg_escape_string" car valeur maîtrisée par l'utilisateur
            if ($id) {
                $personne = new Personne();
                $personne->charge($id);    
                
                $role_connexion = (defined('BASENAME') ? BASENAME : DBNAME); 
                
                // Cryptage RSA classique des variables de session
                if (CRYPTAGE) {
                    $_SESSION[APPLI]['numerisateur']['code'] = $rsa_obj->encrypt($personne->id_personne, $public_key);
                    $_SESSION[APPLI]['numerisateur']['libelle'] = $rsa_obj->encrypt($personne->nom . ' ' . $personne->prenom, $public_key);
                    $_SESSION[APPLI]['numerisateur']['droit'] = $rsa_obj->encrypt($personne->role, $public_key);
                    $_SESSION[APPLI]['numerisateur']['profil'] = $rsa_obj->encrypt($personne->specialite, $public_key);
                    $_SESSION[APPLI]['numerisateur']['idSociete'] = $rsa_obj->encrypt($_POST['id_structure'], $public_key);
                    $_SESSION[APPLI]['numerisateur']['nomSociete'] = $rsa_obj->encrypt($_POST['nom_structure'], $public_key);
                    $_SESSION[APPLI]['Connexion']['LOGIN'] = $rsa_obj->encrypt($personne->email, $public_key);
                    $_SESSION[APPLI]['Connexion']['USER'] = $rsa_obj->encrypt($role_connexion . '_' . $personne->role, $public_key);
                    $_SESSION[APPLI]['Connexion']['PASSWORD'] = $rsa_obj->encrypt($role_connexion . '_' . $personne->role, $public_key);
                }
                else {
                    $_SESSION[APPLI]['numerisateur']['code'] = $personne->id_personne;
                    $_SESSION[APPLI]['numerisateur']['libelle'] = $personne->nom . ' ' . $personne->prenom;
                    $_SESSION[APPLI]['numerisateur']['droit'] = $personne->role;
                    $_SESSION[APPLI]['numerisateur']['profil'] = $personne->specialite;
                    $_SESSION[APPLI]['numerisateur']['idSociete'] = $_POST['id_structure'];
                    $_SESSION[APPLI]['numerisateur']['nomSociete'] = $_POST['nom_structure'];
                    $_SESSION[APPLI]['Connexion']['LOGIN'] = $personne->email;
                    $_SESSION[APPLI]['Connexion']['USER'] = $role_connexion . '_' . $personne->role;
                    $_SESSION[APPLI]['Connexion']['PASSWORD'] = $role_connexion . '_' . $personne->role;
                }
                $data = 'Bienvenue ' . $personne->prenom . ' !!!';
                die('{success: true, data: "' . $data . '"}');
            }
            else {
                $_SESSION[APPLI]['numerisateur'] = array();
                $_SESSION[APPLI]['Connexion'] = array();
                $errorMessage = 'Authentification échouée';
                $data = "Veuillez recommencer l'opération";
                die('{success: false, errorMessage: "' . $errorMessage . '", data: "' . $data . '"}');
            }
            break;
        case 'AttendreSaisie':
                $_SESSION[APPLI]['saisieEnCours'] = $_POST['saisieEnCours'];
            break;
        case 'DetecterAdmin':
            if (CRYPTAGE) {
                // Décryptage RSA personnalisé du mot de passe utilisateur pour authentification
                $rsa_obj_perso = new Crypt_myRSA(null, 'BCMath');
                $private_key_perso = new Crypt_RSA_myKey($_SESSION[APPLI]['Securite']['private_module_perso'], $_SESSION[APPLI]['Securite']['private_exp_perso'], 'private', 'BCMath');
                $mot_de_passe = pg_escape_string($rsa_obj_perso->decrypt($_POST['mot_de_passe'], $private_key_perso));// besoin de "pg_escape_string" car valeur maîtrisée par l'utilisateur
                // Regénération de nouvelles clés RSA classiques
                $nbBits = 128;
                $key_pair = new Crypt_RSA_KeyPair($nbBits, 'BCMath');
                $public_key = $key_pair->getPublicKey();
                $private_key = $key_pair->getPrivateKey();
                // Remplacement en session par la clé privée classique
                $_SESSION[APPLI]['Securite']['private_module'] = $private_key->getModulus();
                $_SESSION[APPLI]['Securite']['private_exp'] = $private_key->getExponent();
                // Cryptage RSA classique des variables de session utilisées dans le constructeur de "CnxPgObsOcc" appelé par "Personne::authentifie"
                $rsa_obj = new Crypt_RSA(null, 'BCMath');
                $_SESSION[APPLI]['Connexion']['USER'] = $rsa_obj->encrypt(USER, $public_key);
                $_SESSION[APPLI]['Connexion']['PASSWORD'] = $rsa_obj->encrypt(PASSWORD, $public_key);
            }
            else {
                $_SESSION[APPLI]['Connexion']['USER'] = USER;
                $_SESSION[APPLI]['Connexion']['PASSWORD'] = PASSWORD;
                $mot_de_passe = pg_escape_string($_POST['mot_de_passe']);
            }
            // Authentification utilisateur avec passage du mot de passe crypté MD5 (protection SGDB)
            $id = Personne::authentifie(pg_escape_string($_POST['email']), md5($mot_de_passe));// besoin de "pg_escape_string" car valeur maîtrisée par l'utilisateur
            if ($id) {
                $personne = new Personne();
                $personne->charge($id);
                if ($personne->role == 'admin') {
                    if (CRYPTAGE) {
                        $_SESSION[APPLI]['numerisateur']['droit'] = $rsa_obj->encrypt($personne->role, $public_key);
                    }
                    else {
                        $_SESSION[APPLI]['numerisateur']['droit'] = $personne->role;
                    }
                    $data = 'Accès à la création de compte autorisé !!!';
                    die('{success: true, data: "' . $data . '"}');
                }
                else {
                    $_SESSION[APPLI]['numerisateur'] = array();
                    $_SESSION[APPLI]['Connexion'] = array();
                    $errorMessage = 'Compte administrateur non détecté';
                    $data = 'Accès à la création de compte non autorisé !!!';
                    die('{success: false, errorMessage: "' . $errorMessage . '", data: "' . $data . '"}');
                }
            }
            else {
                $_SESSION[APPLI]['numerisateur'] = array();
                $_SESSION[APPLI]['Connexion'] = array();
                $errorMessage = 'Authentification échouée';
                $data = 'Accès à la création de compte non autorisé !!!';
                die('{success: false, errorMessage: "' . $errorMessage . '", data: "' . $data . '"}');
            }
            break;
        }
?>
