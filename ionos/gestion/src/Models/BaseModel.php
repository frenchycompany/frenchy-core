<?php
/**
 * BaseModel - Modèle de base pour tous les modèles
 * Fournit des méthodes CRUD communes
 */

abstract class BaseModel
{
    protected $db;
    protected $table;
    protected $primaryKey = 'id';
    protected $timestamps = false;
    protected $fillable = [];
    protected $hidden = [];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Récupère tous les enregistrements
     *
     * @param array $columns Colonnes à sélectionner
     * @return array
     */
    public function all($columns = ['*'])
    {
        $cols = implode(', ', $columns);
        $query = "SELECT {$cols} FROM {$this->table}";
        return $this->db->query($query);
    }

    /**
     * Récupère un enregistrement par son ID
     *
     * @param mixed $id
     * @return array|null
     */
    public function find($id)
    {
        $query = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ? LIMIT 1";
        return $this->db->queryOne($query, [$id]);
    }

    /**
     * Récupère le premier enregistrement correspondant aux conditions
     *
     * @param array $where Conditions WHERE (colonne => valeur)
     * @return array|null
     */
    public function findWhere($where)
    {
        $conditions = [];
        foreach (array_keys($where) as $column) {
            $conditions[] = "{$column} = ?";
        }

        $query = "SELECT * FROM {$this->table} WHERE " . implode(' AND ', $conditions) . " LIMIT 1";
        return $this->db->queryOne($query, array_values($where));
    }

    /**
     * Récupère tous les enregistrements correspondant aux conditions
     *
     * @param array $where Conditions WHERE (colonne => valeur)
     * @param array $columns Colonnes à sélectionner
     * @return array
     */
    public function where($where, $columns = ['*'])
    {
        $cols = implode(', ', $columns);
        $conditions = [];

        foreach (array_keys($where) as $column) {
            $conditions[] = "{$column} = ?";
        }

        $query = "SELECT {$cols} FROM {$this->table} WHERE " . implode(' AND ', $conditions);
        return $this->db->query($query, array_values($where));
    }

    /**
     * Crée un nouvel enregistrement
     *
     * @param array $data Données à insérer
     * @return int|false ID inséré ou false en cas d'erreur
     */
    public function create($data)
    {
        // Filtrer les données selon $fillable si défini
        if (!empty($this->fillable)) {
            $data = array_intersect_key($data, array_flip($this->fillable));
        }

        // Ajouter les timestamps si activés
        if ($this->timestamps) {
            $now = date('Y-m-d H:i:s');
            $data['created_at'] = $now;
            $data['updated_at'] = $now;
        }

        return $this->db->insert($this->table, $data);
    }

    /**
     * Met à jour un enregistrement
     *
     * @param mixed $id ID de l'enregistrement
     * @param array $data Données à mettre à jour
     * @return int Nombre de lignes affectées
     */
    public function update($id, $data)
    {
        // Filtrer les données selon $fillable si défini
        if (!empty($this->fillable)) {
            $data = array_intersect_key($data, array_flip($this->fillable));
        }

        // Ajouter updated_at si timestamps activés
        if ($this->timestamps) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        return $this->db->update($this->table, $data, [$this->primaryKey => $id]);
    }

    /**
     * Met à jour des enregistrements selon des conditions
     *
     * @param array $where Conditions WHERE
     * @param array $data Données à mettre à jour
     * @return int Nombre de lignes affectées
     */
    public function updateWhere($where, $data)
    {
        // Filtrer les données selon $fillable si défini
        if (!empty($this->fillable)) {
            $data = array_intersect_key($data, array_flip($this->fillable));
        }

        // Ajouter updated_at si timestamps activés
        if ($this->timestamps) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        return $this->db->update($this->table, $data, $where);
    }

    /**
     * Supprime un enregistrement par son ID
     *
     * @param mixed $id
     * @return int Nombre de lignes supprimées
     */
    public function delete($id)
    {
        return $this->db->delete($this->table, [$this->primaryKey => $id]);
    }

    /**
     * Supprime des enregistrements selon des conditions
     *
     * @param array $where Conditions WHERE
     * @return int Nombre de lignes supprimées
     */
    public function deleteWhere($where)
    {
        return $this->db->delete($this->table, $where);
    }

    /**
     * Compte le nombre d'enregistrements
     *
     * @param array $where Conditions WHERE optionnelles
     * @return int
     */
    public function count($where = [])
    {
        return $this->db->count($this->table, $where);
    }

    /**
     * Vérifie si un enregistrement existe
     *
     * @param array $where Conditions WHERE
     * @return bool
     */
    public function exists($where)
    {
        return $this->count($where) > 0;
    }

    /**
     * Récupère des enregistrements avec pagination
     *
     * @param int $page Page actuelle
     * @param int $perPage Nombre d'éléments par page
     * @param array $where Conditions WHERE optionnelles
     * @return array ['data' => array, 'total' => int, 'page' => int, 'pages' => int]
     */
    public function paginate($page = 1, $perPage = 20, $where = [])
    {
        $page = max(1, (int)$page);
        $offset = ($page - 1) * $perPage;

        // Compter le total
        $total = $this->count($where);

        // Construire la requête
        $query = "SELECT * FROM {$this->table}";
        $params = [];

        if (!empty($where)) {
            $conditions = [];
            foreach (array_keys($where) as $column) {
                $conditions[] = "{$column} = ?";
            }
            $query .= " WHERE " . implode(' AND ', $conditions);
            $params = array_values($where);
        }

        $query .= " LIMIT {$perPage} OFFSET {$offset}";

        $data = $this->db->query($query, $params);

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => ceil($total / $perPage)
        ];
    }

    /**
     * Exécute une requête SQL personnalisée
     *
     * @param string $query Requête SQL
     * @param array $params Paramètres
     * @return array
     */
    public function query($query, $params = [])
    {
        return $this->db->query($query, $params);
    }

    /**
     * Récupère la connexion à la base de données
     *
     * @return Database
     */
    public function getDb()
    {
        return $this->db;
    }

    /**
     * Filtre les champs cachés d'un enregistrement
     *
     * @param array $data
     * @return array
     */
    protected function hideFields($data)
    {
        if (empty($this->hidden)) {
            return $data;
        }

        return array_diff_key($data, array_flip($this->hidden));
    }

    /**
     * Filtre les champs cachés de plusieurs enregistrements
     *
     * @param array $items
     * @return array
     */
    protected function hideFieldsMany($items)
    {
        if (empty($this->hidden)) {
            return $items;
        }

        return array_map(function($item) {
            return $this->hideFields($item);
        }, $items);
    }
}
