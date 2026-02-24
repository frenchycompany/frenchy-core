<?php
/**
 * AuthMiddleware - Middleware d'authentification
 */

class AuthMiddleware
{
    /**
     * Vérifie si l'utilisateur est authentifié
     *
     * @param callable $next
     * @return mixed
     */
    public static function handle($next = null)
    {
        if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
            // Rediriger vers la page de login
            header('Location: /login.php');
            exit;
        }

        if ($next && is_callable($next)) {
            return $next();
        }

        return true;
    }

    /**
     * Vérifie si l'utilisateur a accès à une page
     *
     * @param int $pageId
     * @return bool
     */
    public static function hasPageAccess($pageId)
    {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        $db = Database::getInstance();

        // Vérifier si l'utilisateur a accès à cette page
        $count = $db->count('intervenants_pages', [
            'intervenant_id' => $_SESSION['user_id'],
            'page_id' => $pageId
        ]);

        return $count > 0;
    }

    /**
     * Vérifie si l'utilisateur a un rôle spécifique
     *
     * @param string $role
     * @return bool
     */
    public static function hasRole($role)
    {
        if (!isset($_SESSION['roles'])) {
            return false;
        }

        return in_array($role, $_SESSION['roles']);
    }

    /**
     * Vérifie si l'utilisateur est administrateur
     *
     * @return bool
     */
    public static function isAdmin()
    {
        return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    }

    /**
     * Requiert un rôle spécifique
     *
     * @param string $role
     */
    public static function requireRole($role)
    {
        if (!self::hasRole($role)) {
            http_response_code(403);
            echo "Accès refusé. Vous n'avez pas les permissions nécessaires.";
            exit;
        }
    }

    /**
     * Requiert un accès administrateur
     */
    public static function requireAdmin()
    {
        if (!self::isAdmin()) {
            http_response_code(403);
            echo "Accès refusé. Droits administrateur requis.";
            exit;
        }
    }
}
