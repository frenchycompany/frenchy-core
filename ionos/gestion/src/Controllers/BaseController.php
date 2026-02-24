<?php
/**
 * BaseController - Contrôleur de base pour tous les contrôleurs
 * Fournit des méthodes communes pour la gestion des requêtes et réponses
 */

class BaseController
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Charge une vue
     *
     * @param string $view Nom de la vue
     * @param array $data Données à passer à la vue
     * @param string $layout Layout à utiliser (null pour pas de layout)
     */
    protected function view($view, $data = [], $layout = 'default')
    {
        // Extraire les données pour qu'elles soient accessibles dans la vue
        extract($data);

        // Buffer de sortie
        ob_start();

        // Charger la vue
        $viewPath = BASE_PATH . "/src/Views/{$view}.php";

        if (!file_exists($viewPath)) {
            throw new Exception("La vue {$view} n'existe pas.");
        }

        include $viewPath;

        $content = ob_get_clean();

        // Si un layout est spécifié, charger le layout avec le contenu
        if ($layout !== null) {
            $layoutPath = BASE_PATH . "/src/Views/layouts/{$layout}.php";

            if (file_exists($layoutPath)) {
                include $layoutPath;
            } else {
                echo $content;
            }
        } else {
            echo $content;
        }
    }

    /**
     * Redirige vers une URL
     *
     * @param string $url URL de destination
     * @param int $statusCode Code HTTP (301, 302, etc.)
     */
    protected function redirect($url, $statusCode = 302)
    {
        header("Location: {$url}", true, $statusCode);
        exit;
    }

    /**
     * Retourne une réponse JSON
     *
     * @param mixed $data Données à retourner
     * @param int $statusCode Code HTTP
     */
    protected function json($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Retourne une réponse JSON de succès
     *
     * @param mixed $data Données
     * @param string $message Message de succès
     */
    protected function jsonSuccess($data = null, $message = 'Succès')
    {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        $this->json($response);
    }

    /**
     * Retourne une réponse JSON d'erreur
     *
     * @param string $message Message d'erreur
     * @param int $statusCode Code HTTP
     * @param mixed $errors Erreurs détaillées (optionnel)
     */
    protected function jsonError($message = 'Erreur', $statusCode = 400, $errors = null)
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        $this->json($response, $statusCode);
    }

    /**
     * Récupère un paramètre de la requête GET
     *
     * @param string $key Clé du paramètre
     * @param mixed $default Valeur par défaut
     * @return mixed
     */
    protected function get($key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * Récupère un paramètre de la requête POST
     *
     * @param string $key Clé du paramètre
     * @param mixed $default Valeur par défaut
     * @return mixed
     */
    protected function post($key, $default = null)
    {
        return $_POST[$key] ?? $default;
    }

    /**
     * Récupère tous les paramètres POST
     *
     * @return array
     */
    protected function postAll()
    {
        return $_POST;
    }

    /**
     * Récupère tous les paramètres GET
     *
     * @return array
     */
    protected function getAll()
    {
        return $_GET;
    }

    /**
     * Vérifie si la requête est POST
     *
     * @return bool
     */
    protected function isPost()
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    /**
     * Vérifie si la requête est GET
     *
     * @return bool
     */
    protected function isGet()
    {
        return $_SERVER['REQUEST_METHOD'] === 'GET';
    }

    /**
     * Vérifie si la requête est AJAX
     *
     * @return bool
     */
    protected function isAjax()
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Valide le token CSRF
     *
     * @return bool
     */
    protected function validateCsrf()
    {
        $token = $this->post('csrf_token');
        return Security::validateCsrfToken($token);
    }

    /**
     * Vérifie si l'utilisateur est connecté
     *
     * @return bool
     */
    protected function isAuthenticated()
    {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Récupère l'utilisateur connecté
     *
     * @return array|null
     */
    protected function currentUser()
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

        return $_SESSION;
    }

    /**
     * Vérifie si l'utilisateur a une permission
     *
     * @param string $permission
     * @return bool
     */
    protected function hasPermission($permission)
    {
        // À implémenter selon votre système de permissions
        return true;
    }

    /**
     * Retourne une erreur 404
     */
    protected function notFound()
    {
        http_response_code(404);
        $this->view('errors/404', [], null);
        exit;
    }

    /**
     * Retourne une erreur 403 (Forbidden)
     */
    protected function forbidden()
    {
        http_response_code(403);
        $this->view('errors/403', [], null);
        exit;
    }

    /**
     * Définit un message flash en session
     *
     * @param string $type Type de message (success, error, warning, info)
     * @param string $message Message à afficher
     */
    protected function flash($type, $message)
    {
        if (!isset($_SESSION['flash_messages'])) {
            $_SESSION['flash_messages'] = [];
        }

        $_SESSION['flash_messages'][] = [
            'type' => $type,
            'message' => $message
        ];
    }

    /**
     * Récupère et supprime les messages flash
     *
     * @return array
     */
    protected function getFlashMessages()
    {
        $messages = $_SESSION['flash_messages'] ?? [];
        unset($_SESSION['flash_messages']);
        return $messages;
    }

    /**
     * Valide des données avec des règles
     *
     * @param array $data Données à valider
     * @param array $rules Règles de validation
     * @return array ['valid' => bool, 'errors' => array]
     */
    protected function validate($data, $rules)
    {
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            $rulesArray = explode('|', $fieldRules);

            foreach ($rulesArray as $rule) {
                $params = [];

                // Parser la règle (ex: min:3)
                if (strpos($rule, ':') !== false) {
                    list($rule, $paramString) = explode(':', $rule, 2);
                    $params = explode(',', $paramString);
                }

                // Appliquer la règle
                switch ($rule) {
                    case 'required':
                        if (empty($value) && $value !== '0') {
                            $errors[$field][] = "Le champ {$field} est requis.";
                        }
                        break;

                    case 'email':
                        if (!empty($value) && !Security::validateEmail($value)) {
                            $errors[$field][] = "Le champ {$field} doit être une adresse email valide.";
                        }
                        break;

                    case 'min':
                        $min = (int)$params[0];
                        if (!empty($value) && strlen($value) < $min) {
                            $errors[$field][] = "Le champ {$field} doit contenir au moins {$min} caractères.";
                        }
                        break;

                    case 'max':
                        $max = (int)$params[0];
                        if (!empty($value) && strlen($value) > $max) {
                            $errors[$field][] = "Le champ {$field} ne doit pas dépasser {$max} caractères.";
                        }
                        break;

                    case 'numeric':
                        if (!empty($value) && !is_numeric($value)) {
                            $errors[$field][] = "Le champ {$field} doit être numérique.";
                        }
                        break;
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}
