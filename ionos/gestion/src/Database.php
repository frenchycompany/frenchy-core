<?php
/**
 * Classe Database - Gestion centralisée de la base de données
 * Pattern Singleton pour avoir une seule instance de connexion
 */

class Database
{
    private static $instance = null;
    private $connection = null;
    private $config = [];

    /**
     * Constructeur privé (Singleton)
     */
    private function __construct($config = null)
    {
        if ($config === null) {
            $this->config = Config::database();
        } else {
            $this->config = $config;
        }

        $this->connect();
    }

    /**
     * Récupère l'instance unique de Database (Singleton)
     *
     * @param array|null $config Configuration optionnelle
     * @return Database
     */
    public static function getInstance($config = null)
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    /**
     * Établit la connexion à la base de données
     */
    private function connect()
    {
        try {
            $dsn = sprintf(
                "mysql:host=%s;dbname=%s;charset=%s",
                $this->config['host'],
                $this->config['name'],
                $this->config['charset']
            );

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->config['charset']}"
            ];

            $this->connection = new PDO(
                $dsn,
                $this->config['user'],
                $this->config['password'],
                $options
            );

        } catch (PDOException $e) {
            $this->handleError($e);
        }
    }

    /**
     * Récupère la connexion PDO
     *
     * @return PDO
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Exécute une requête de sélection
     *
     * @param string $query Requête SQL
     * @param array $params Paramètres de la requête
     * @return array
     */
    public function query($query, $params = [])
    {
        try {
            $stmt = $this->prepare($query, $params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->handleError($e);
            return [];
        }
    }

    /**
     * Exécute une requête et retourne une seule ligne
     *
     * @param string $query Requête SQL
     * @param array $params Paramètres de la requête
     * @return array|null
     */
    public function queryOne($query, $params = [])
    {
        try {
            $stmt = $this->prepare($query, $params);
            $result = $stmt->fetch();
            return $result ?: null;
        } catch (PDOException $e) {
            $this->handleError($e);
            return null;
        }
    }

    /**
     * Exécute une requête et retourne une seule valeur
     *
     * @param string $query Requête SQL
     * @param array $params Paramètres de la requête
     * @return mixed
     */
    public function queryValue($query, $params = [])
    {
        try {
            $stmt = $this->prepare($query, $params);
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            $this->handleError($e);
            return null;
        }
    }

    /**
     * Exécute une requête d'insertion, mise à jour ou suppression
     *
     * @param string $query Requête SQL
     * @param array $params Paramètres de la requête
     * @return bool
     */
    public function execute($query, $params = [])
    {
        try {
            $stmt = $this->prepare($query, $params);
            return true;
        } catch (PDOException $e) {
            $this->handleError($e);
            return false;
        }
    }

    /**
     * Insère un enregistrement et retourne l'ID
     *
     * @param string $table Nom de la table
     * @param array $data Données à insérer (colonne => valeur)
     * @return int|false ID inséré ou false en cas d'erreur
     */
    public function insert($table, $data)
    {
        try {
            $columns = array_keys($data);
            $placeholders = array_fill(0, count($columns), '?');

            $query = sprintf(
                "INSERT INTO %s (%s) VALUES (%s)",
                $table,
                implode(', ', $columns),
                implode(', ', $placeholders)
            );

            $stmt = $this->connection->prepare($query);
            $stmt->execute(array_values($data));

            return (int) $this->connection->lastInsertId();
        } catch (PDOException $e) {
            $this->handleError($e);
            return false;
        }
    }

    /**
     * Met à jour un ou plusieurs enregistrements
     *
     * @param string $table Nom de la table
     * @param array $data Données à mettre à jour (colonne => valeur)
     * @param array $where Conditions WHERE (colonne => valeur)
     * @return int Nombre de lignes affectées
     */
    public function update($table, $data, $where)
    {
        try {
            $set = [];
            foreach (array_keys($data) as $column) {
                $set[] = "$column = ?";
            }

            $whereClause = [];
            foreach (array_keys($where) as $column) {
                $whereClause[] = "$column = ?";
            }

            $query = sprintf(
                "UPDATE %s SET %s WHERE %s",
                $table,
                implode(', ', $set),
                implode(' AND ', $whereClause)
            );

            $params = array_merge(array_values($data), array_values($where));
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);

            return $stmt->rowCount();
        } catch (PDOException $e) {
            $this->handleError($e);
            return 0;
        }
    }

    /**
     * Supprime un ou plusieurs enregistrements
     *
     * @param string $table Nom de la table
     * @param array $where Conditions WHERE (colonne => valeur)
     * @return int Nombre de lignes supprimées
     */
    public function delete($table, $where)
    {
        try {
            $whereClause = [];
            foreach (array_keys($where) as $column) {
                $whereClause[] = "$column = ?";
            }

            $query = sprintf(
                "DELETE FROM %s WHERE %s",
                $table,
                implode(' AND ', $whereClause)
            );

            $stmt = $this->connection->prepare($query);
            $stmt->execute(array_values($where));

            return $stmt->rowCount();
        } catch (PDOException $e) {
            $this->handleError($e);
            return 0;
        }
    }

    /**
     * Prépare et exécute une requête
     *
     * @param string $query Requête SQL
     * @param array $params Paramètres de la requête
     * @return PDOStatement
     */
    private function prepare($query, $params = [])
    {
        $stmt = $this->connection->prepare($query);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Démarre une transaction
     */
    public function beginTransaction()
    {
        $this->connection->beginTransaction();
    }

    /**
     * Valide une transaction
     */
    public function commit()
    {
        $this->connection->commit();
    }

    /**
     * Annule une transaction
     */
    public function rollback()
    {
        $this->connection->rollBack();
    }

    /**
     * Vérifie si une transaction est en cours
     *
     * @return bool
     */
    public function inTransaction()
    {
        return $this->connection->inTransaction();
    }

    /**
     * Récupère le dernier ID inséré
     *
     * @return string
     */
    public function lastInsertId()
    {
        return $this->connection->lastInsertId();
    }

    /**
     * Compte le nombre d'enregistrements
     *
     * @param string $table Nom de la table
     * @param array $where Conditions WHERE optionnelles
     * @return int
     */
    public function count($table, $where = [])
    {
        $query = "SELECT COUNT(*) FROM $table";

        if (!empty($where)) {
            $whereClause = [];
            foreach (array_keys($where) as $column) {
                $whereClause[] = "$column = ?";
            }
            $query .= " WHERE " . implode(' AND ', $whereClause);
        }

        return (int) $this->queryValue($query, array_values($where));
    }

    /**
     * Gère les erreurs de base de données
     *
     * @param PDOException $e
     * @throws PDOException
     */
    private function handleError(PDOException $e)
    {
        // Logger l'erreur
        logMessage("Database Error: " . $e->getMessage(), 'error');

        if (Config::isProduction()) {
            throw new PDOException("Une erreur de base de données s'est produite.");
        } else {
            throw $e;
        }
    }

    /**
     * Empêche le clonage de l'instance
     */
    private function __clone() {}

    /**
     * Empêche la désérialisation de l'instance
     */
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }
}
