<?php
/*require_once("model/Survey.inc.php");
require_once("model/Response.inc.php");*/


class Database
{
    private $connection;

    /**
     * Ouvre la base de données. Si la base n'existe pas elle
     * est créée à l'aide de la méthode createDataBase().
     */
    public function __construct()
    {
        $dbHost = "localhost";
        $dbBd = "sondages";
        $dbPass = "Asperge23@";
        $dbLogin = "root";
        $url = 'mysql:host=' . $dbHost;
        //$url = 'sqlite:database.sqlite';
        $this->connection = new PDO($url, $dbLogin, $dbPass);
        if (!$this->connection) die("impossible d'ouvrir la base de données");
        $this->createDataBase();
    }


    /**
     * Initialise la base de données ouverte dans la variable $connection.
     * Cette méthode crée, si elles n'existent pas, les trois tables :
     * - une table users(nickname char(20), password char(50));
     * - une table surveys(id integer primary key autoincrement,
     *                        owner char(20), question char(255));
     * - une table responses(id integer primary key autoincrement,
     *        id_survey integer,
     *        title char(255),
     *        count integer);
     */
    private function createDataBase()
    {
        $this->connection->exec("CREATE DATABASE IF NOT EXISTS sondages CHARACTER SET utf8;");

        $this->connection->exec("USE sondages;");

        $this->connection->exec("CREATE TABLE IF NOT EXISTS users (
									nickname CHAR(20) NOT NULL ,
									nom CHAR(64) NOT NULL ,
									prenom CHAR(32) NOT NULL ,
									mail VARCHAR(128) NOT NULL ,
									avatar VARCHAR(148),
									password CHAR(50) NOT NULL ) ENGINE = InnoDB;");

        $this->connection->exec("CREATE TABLE IF NOT EXISTS surveys (
									id INT NOT NULL AUTO_INCREMENT ,
									owner CHAR(20) NOT NULL ,
									question CHAR(255) NOT NULL ,
									PRIMARY KEY (id)) ENGINE = InnoDB;");

        $this->connection->exec("CREATE TABLE IF NOT EXISTS responses (
									id INT NOT NULL AUTO_INCREMENT ,
									id_survey INT NOT NULL,
									title CHAR(255) NOT NULL ,
									count INT,
									PRIMARY KEY (id)) ENGINE = InnoDB;");
	          
        $this->connection->exec("CREATE TABLE IF NOT EXISTS comments (
									id INT NOT NULL AUTO_INCREMENT ,
									id_survey INT NOT NULL ,
									owner CHAR(20) NOT NULL ,
									comment CHAR(255) NOT NULL ,
									PRIMARY KEY (id)) ENGINE = InnoDB;");
    }

    /**
     * Vérifie si un pseudonyme est valide, c'est-à-dire,
     * s'il contient entre 3 et 10 caractères et uniquement des lettres.
     *
     * @param string $nickname Pseudonyme à vérifier.
     * @return boolean True si le pseudonyme est valide, false sinon.
     */
    private function checkNicknameValidity($nickname)
    {
        if (strlen($nickname) < 3 || strlen($nickname) > 10) {
            return false;
        }
        $parser = count_chars($nickname);

        for ($i = 0; $i < 65; $i++) {
            if ($parser[$i] > 0) {
                return false;
            }
        }

        for ($i = 91; $i < 97; $i++) {
            if ($parser[$i] > 0) {
                return false;
            }
        }

        for ($i = 123; $i < 255; $i++) {
            if ($parser[$i] > 0) {
                return false;
            }
        }

        return true;
    }

    private function checkMailValidity($mail) {
        $parse = explode("@",$mail);

        if(count($parse) == 2) {
            return true;
        }

        return false;
    }

    /**
     * Vérifie si un mot de passe est valide, c'est-à-dire,
     * s'il contient entre 3 et 10 caractères.
     *
     * @param string $password Mot de passe à vérifier.
     * @return boolean True si le mot de passe est valide, false sinon.
     */
    private function checkPasswordValidity($password)
    {
        if (strlen($password) < 3 || strlen($password) > 10) {
            return false;
        }
        return true;
    }

    /**
     * Vérifie la disponibilité d'un pseudonyme.
     *
     * @param string $nickname Pseudonyme à vérifier.
     * @return boolean True si le pseudonyme est disponible, false sinon.
     */
    private function checkNicknameAvailability($nickname)
    {
        $requete = "SELECT nickname FROM users";

        foreach ($this->connection->query($requete) as $row) {
            $noms[] = $row['nickname'];
        }
		
		if(isset($noms)) {
			foreach ($noms as $row) {
				if ($nickname == $row) {
					return false;
				}
			}
		}
        return true;
    }

    private function checkMailAvailability($mail)
    {
        $requete = "SELECT mail FROM users";

        foreach ($this->connection->query($requete) as $row) {
            $mails[] = $row['mail'];
        }

		if(isset($mails)) {
			foreach ($mails as $row) {
				if ($mail == $row) {
					return false;
				}
			}
		}
        return true;
    }

    private function hashpass($string)
    {
        $cle1 = 'r5fsj6D5gkM0';
        $cle2 = '4rnDoe5ePfl2';
        return sha1($cle1 . $string . $cle2);
    }

    /**
     * Vérifie qu'un couple (pseudonyme, mot de passe) est correct.
     *
     * @param string $nickname Pseudonyme.
     * @param string $password Mot de passe.
     * @return boolean True si le couple est correct, false sinon.
     */
    public function checkPassword($nickname, $password)
    {
		if($nickname!='' && $password!='') {
			$requete = "SELECT password FROM users WHERE nickname = '$nickname'";

			foreach ($this->connection->query($requete) as $row) {
				$mdp = $row['password'];
			}

			if(isset($mdp)) {
				if ($this->hashpass($password) == $mdp) {
					return true;
				} else {
					return false;
				}
			}
		}
		return false;
    }

    /**
     * Ajoute un nouveau compte utilisateur si le pseudonyme est valide et disponible et
     * si le mot de passe est valide. La méthode peut retourner un des messages d'erreur qui suivent :
     * - "Le pseudo doit contenir entre 3 et 10 lettres.";
     * - "Le mot de passe doit contenir entre 3 et 10 caractères.";
     * - "Le pseudo existe déjà.".
     *
     * @param string $nickname Pseudonyme.
     * @param string $password Mot de passe.
     * @return boolean|string True si le couple a été ajouté avec succès, un message d'erreur sinon.
     */
    public function addUser($nickname, $password, $fName , $lName, $mail)
    {
        $validitycheckNickname = $this->checkNicknameValidity($nickname);
        $availabilitycheckNickname = $this->checkNicknameAvailability($nickname);
        $validitycheckMail = $this->checkMailValidity($mail);
        $availabilitycheckMail = $this->checkMailAvailability($mail);
        $validitycheckPassword = $this->checkPasswordValidity($password);

        if (!$validitycheckNickname) {
            return "Le pseudo doit contenir entre 3 et 10 lettres.";
        }

        if (!$availabilitycheckNickname) {
            return "Le pseudo existe déjà.";
        }

        if (!$validitycheckMail) {
            return "L'adresse mail n'est pas valide.";
        }

        if (!$availabilitycheckMail) {
            return "Il existe déjà un compte lié à cette adresse mail.";
        }

        if (!$validitycheckPassword) {
            return "Le mot de passe doit contenir entre 3 et 10 caractères.";
        }

        $this->connection->exec("INSERT INTO users (nickname, nom, prenom, mail, avatar, password) VALUES ('$nickname', '$lName' , '$fName' , '$mail' ,null, '" . $this->hashpass($password) . "');");

        return true;
    }

    public function insertAvatar($image,$nomuser) {
        $this->connection->exec("UPDATE users set avatar='$image' WHERE nickname='$nomuser'");
    }

	public function addComment($owner, $idsurvey,$comment){
		var_dump("INSERT INTO comments VALUES (null,$idsurvey,\"$owner\",\"$comment\");");
        if($this->connection->exec("INSERT INTO comments VALUES (null,$idsurvey,\"$owner\",\"$comment\");") !== null) {
			return true;
		} else {
			return false;
		}
    }
	
	public function loadComment($id){
		if(true) {
			$requete = "SELECT * FROM comments WHERE id_survey = '$id'";
			$retour = array();
			$retour[0] = array();
			$retour[1] = array();

			foreach ($this->connection->query($requete) as $row) {
				array_push($retour[0],$row['owner']);
				array_push($retour[1],$row['comment']);
			}
			return $retour;
		} else {
			return false;
		}
	}

    /**
     * Change le mot de passe d'un utilisateur.
     * La fonction vérifie si le mot de passe est valide. S'il ne l'est pas,
     * la fonction retourne le texte 'Le mot de passe doit contenir entre 3 et 10 caractères.'.
     * Sinon, le mot de passe est modifié en base de données et la fonction retourne true.
     *
     * @param string $nickname Pseudonyme de l'utilisateur.
     * @param string $password Nouveau mot de passe.
     * @return boolean|string True si le mot de passe a été modifié, un message d'erreur sinon.
     */
    public function updateUser($nickname, $password)
    {
        $elbool = $this->checkPasswordValidity($password);
        if ($elbool === true) {
            $this->connection->exec("UPDATE users 
										SET users.password=\"" . $this->hashpass($password) . "\"
										WHERE nickname=\"$nickname\"");
            return true;
        } else {
            return "Le mot de passe doit contenir entre 3 et 10 caractères.";
        }
        return true;
    }

    /**
     * Sauvegarde un sondage dans la base de donnée et met à jour les indentifiants
     * du sondage et des réponses.
     *
     * @param Survey $survey Sondage à sauvegarder.
     * @return boolean True si la sauvegarde a été réalisée avec succès, false sinon.
     */
    public function saveSurvey($survey)
    {
        if ($this->connection->exec("INSERT INTO surveys 
									VALUES (" . $survey->getId() . ",'" . $survey->getOwner() . "','" . $survey->getQuestion() . "')")) {
			$survey->setId($this->connection->lastInsertId());
            return true;
        } else {
            return false;
        }
    }

    /**
     * Sauvegarde une réponse dans la base de donnée et met à jour son indentifiant.
     *
     * @param Response $response Réponse à sauvegarder.
     * @return boolean True si la sauvegarde a été réalisée avec succès, false sinon.
     */
    public function saveResponse($response)
    {
        if ($this->connection->exec("INSERT INTO responses 
									VALUES ( null" . $response->getId() . "," . $response->getSurvey()->getId() . ",'" . $response->getTitle() . "','" . $response->getCount() . "')")) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Charge l'ensemble des sondages créés par un utilisateur.
     *
     * @param string $owner Pseudonyme de l'utilisateur.
     * @return array(Survey)|boolean Sondages trouvés par la fonction ou false si une erreur s'est produite.
     */
    public function loadSurveysByOwner($owner){
		$requete = "SELECT surveys.id as idsurv, surveys.question, responses.title, responses.count, responses.id FROM surveys INNER JOIN responses ON surveys.id = responses.id_survey WHERE surveys.owner = \"$owner\"";
		$result = $this->connection->query($requete);
		if ($result === false) {
            return false;
        } else {
			return $result;
		}
    }

    /**
     * Charge l'ensemble des sondages dont la question contient un mot clé.
     *
     * @param string $keyword Mot clé à chercher.
     * @return array(Survey)|boolean Sondages trouvés par la fonction ou false si une erreur s'est produite.
     */
    public function loadSurveysByKeyword($keyword)
    {
		$requete = "SELECT surveys.id as idsurv, surveys.question, surveys.owner, responses.title, responses.count, responses.id FROM surveys INNER JOIN responses ON surveys.id = responses.id_survey WHERE surveys.question LIKE '%$keyword%'";
        $result = $this->connection->query($requete);
		if ($result === false) {
            return false;
        } else {
			return $result;
		}
    }


    /**
     * Enregistre le vote d'un utilisateur pour la réponse d'identifiant $id.
     *
     * @param int $id Identifiant de la réponse.
     * @return boolean True si le vote a été enregistré, false sinon.
     */
    public function vote($id)
    {
        if($this->connection->exec("UPDATE responses SET count = count + 1 WHERE id = $id")) {
			return true;
		} else {
			return false;
		}
    }

    /**
     * Construit un tableau de sondages à partir d'un tableau de ligne de la table 'surveys'.
     * Ce tableau a été obtenu à l'aide de la méthode fetchAll() de PDO.
     *
     * @param array $arraySurveys Tableau de lignes.
     * @return array(Survey)|boolean Le tableau de sondages ou false si une erreur s'est produite.
     */
    private function loadSurveys($arraySurveys)
    {
        $surveys = array();
        /* TODO START */
        /* TODO END */
        return $surveys;
    }

    /**
     * Construit un tableau de réponses à partir d'un tableau de ligne de la table 'responses'.
     * Ce tableau a été obtenu à l'aide de la méthode fetchAll() de PDO.
     *
     * @param Survey $survey Le sondage.
     * @param array $arraySurveys Tableau de lignes.
     * @return array(Response)|boolean Le tableau de réponses ou false si une erreur s'est produite.
     */
    private function loadResponses($survey, $arrayResponses)
    {
        $responses = array();
        /* TODO START */
        /* TODO END */
        return $responses;
    }

    public function loadSurveyById($id)
    {
		$requete = "SELECT * FROM surveys WHERE id = $id";
        $result = $this->connection->query($requete);
		foreach($result as $foo) {
			$survey = new Survey($foo['owner'], $foo['question']);
			$survey->setId(intval($foo['id']));
			$survey->setResponses($this->loadResponsesBySurveyId($id, $survey));
		}
        return $survey;
    }

    private function loadResponsesBySurveyId($id, $survey)
    {
		$requete = "SELECT * FROM responses WHERE id_survey = $id";
		$responses = array();
		$result = $this->connection->query($requete);
		foreach($result as $bar) {
			$pusher = new Response($survey, $bar['title']);
			$pusher->setId(intval($bar['id']));
			array_push($responses,$pusher);
		}
		if(count($responses) < 5) {
			for($i = 0; $i < (5 - count($responses)); $i++){
				array_push($responses,new Response($survey, ""));
			}
		}
        return $responses;
    }
    /**
     * modifie le contenu de l'enoncé d'un sondage dans la base de donnée et met à jour les indentifiants
     * du sondage et des réponses.
     *
     * @param Survey $survey Sondage à modifier.
     * @return boolean True si la modification a été réalisée avec succès, false sinon.
     */
    public function updateSurvey($id, $newQuestion)
    {
        if ($this->connection->exec("UPDATE surveys SET question='$newQuestion' WHERE id = $id")) {
            return true;
        } else {
			return false;
		}
    }


    public function updateResponse($id, $newResponse)
    {
        if ($this->connection->exec("UPDATE responses SET title='$newResponse', count=0 WHERE id = $id")) {
            return true;
        } else {
			return false;
		}

    }


    public function deleteSurvey($id, $user){
		if ($this->connection->exec("DELETE FROM surveys WHERE id = $id AND owner = '$user'")) {
				return true;
			} else {
				return false;
			}
    }
	
    public function deleteResponse($id){
		if ($this->connection->exec("DELETE FROM responses WHERE id = $id")) {
				return true;
			} else {
				return false;
			}
    }

}


?>
