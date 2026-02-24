<?php
/**
 * InventaireService - Logique métier pour la gestion des inventaires
 */

class InventaireService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Crée une nouvelle session d'inventaire
     *
     * @param int $logementId
     * @param int $userId
     * @return int|false
     */
    public function creerSession($logementId, $userId)
    {
        $session = [
            'logement_id' => $logementId,
            'user_id' => $userId,
            'date_debut' => date('Y-m-d H:i:s'),
            'statut' => 'en_cours',
        ];

        return $this->db->insert('sessions_inventaire', $session);
    }

    /**
     * Récupère une session d'inventaire
     *
     * @param int $sessionId
     * @return array|null
     */
    public function getSession($sessionId)
    {
        $query = "
            SELECT s.*, l.nom as logement_nom, i.nom as user_nom
            FROM sessions_inventaire s
            LEFT JOIN liste_logements l ON s.logement_id = l.id
            LEFT JOIN intervenant i ON s.user_id = i.id
            WHERE s.id = ?
        ";

        return $this->db->queryOne($query, [$sessionId]);
    }

    /**
     * Récupère les sessions d'inventaire d'un logement
     *
     * @param int $logementId
     * @return array
     */
    public function getSessionsLogement($logementId)
    {
        $query = "
            SELECT s.*, i.nom as user_nom
            FROM sessions_inventaire s
            LEFT JOIN intervenant i ON s.user_id = i.id
            WHERE s.logement_id = ?
            ORDER BY s.date_debut DESC
        ";

        return $this->db->query($query, [$logementId]);
    }

    /**
     * Ajoute un objet à l'inventaire
     *
     * @param array $data
     * @return int|false
     */
    public function ajouterObjet($data)
    {
        $objet = [
            'session_id' => $data['session_id'],
            'nom' => $data['nom'],
            'quantite' => $data['quantite'] ?? 1,
            'marque' => $data['marque'] ?? null,
            'etat' => $data['etat'] ?? 'Bon',
            'date_acquisition' => $data['date_acquisition'] ?? null,
            'valeur' => $data['valeur'] ?? null,
            'photo' => $data['photo'] ?? null,
            'remarques' => $data['remarques'] ?? null,
        ];

        return $this->db->insert('inventaire_objets', $objet);
    }

    /**
     * Met à jour un objet de l'inventaire
     *
     * @param int $id
     * @param array $data
     * @return int
     */
    public function mettreAJourObjet($id, $data)
    {
        return $this->db->update('inventaire_objets', $data, ['id' => $id]);
    }

    /**
     * Supprime un objet de l'inventaire
     *
     * @param int $id
     * @return int
     */
    public function supprimerObjet($id)
    {
        return $this->db->delete('inventaire_objets', ['id' => $id]);
    }

    /**
     * Récupère les objets d'une session
     *
     * @param int $sessionId
     * @return array
     */
    public function getObjetsSession($sessionId)
    {
        $query = "
            SELECT * FROM inventaire_objets
            WHERE session_id = ?
            ORDER BY nom ASC
        ";

        return $this->db->query($query, [$sessionId]);
    }

    /**
     * Valide une session d'inventaire
     *
     * @param int $sessionId
     * @return int
     */
    public function validerSession($sessionId)
    {
        return $this->db->update('sessions_inventaire', [
            'statut' => 'validee',
            'date_fin' => date('Y-m-d H:i:s'),
        ], ['id' => $sessionId]);
    }

    /**
     * Génère un QR code pour un logement
     *
     * @param int $logementId
     * @return string|false Chemin du QR code ou false
     */
    public function genererQRCode($logementId)
    {
        // URL vers la page d'inventaire
        $url = Config::get('APP_URL', 'http://localhost') . "/inventaire.php?logement_id={$logementId}";

        // Utiliser une bibliothèque de QR code ou un service externe
        // Pour cet exemple, on utilise l'API de Google Charts (à remplacer par une lib locale en production)
        $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($url);

        // Télécharger et sauvegarder le QR code
        $qrCodeData = file_get_contents($qrCodeUrl);

        if ($qrCodeData === false) {
            return false;
        }

        $filename = "qrcode_logement_{$logementId}.png";
        $filepath = BASE_PATH . "/uploads/qrcodes/" . $filename;

        file_put_contents($filepath, $qrCodeData);

        return "uploads/qrcodes/" . $filename;
    }

    /**
     * Récupère l'inventaire complet d'un logement (dernière session validée)
     *
     * @param int $logementId
     * @return array|null
     */
    public function getInventaireActuel($logementId)
    {
        // Récupérer la dernière session validée
        $session = $this->db->queryOne(
            "SELECT * FROM sessions_inventaire
             WHERE logement_id = ? AND statut = 'validee'
             ORDER BY date_fin DESC
             LIMIT 1",
            [$logementId]
        );

        if (!$session) {
            return null;
        }

        // Récupérer les objets
        $objets = $this->getObjetsSession($session['id']);

        return [
            'session' => $session,
            'objets' => $objets,
        ];
    }

    /**
     * Calcule la valeur totale de l'inventaire d'un logement
     *
     * @param int $logementId
     * @return float
     */
    public function getValeurTotale($logementId)
    {
        $inventaire = $this->getInventaireActuel($logementId);

        if (!$inventaire) {
            return 0;
        }

        $total = 0;

        foreach ($inventaire['objets'] as $objet) {
            if ($objet['valeur']) {
                $total += $objet['valeur'] * $objet['quantite'];
            }
        }

        return $total;
    }

    /**
     * Compare deux inventaires pour détecter les différences
     *
     * @param int $sessionId1
     * @param int $sessionId2
     * @return array
     */
    public function comparerInventaires($sessionId1, $sessionId2)
    {
        $objets1 = $this->getObjetsSession($sessionId1);
        $objets2 = $this->getObjetsSession($sessionId2);

        $differences = [
            'ajoutes' => [],
            'supprimes' => [],
            'modifies' => [],
        ];

        // Créer des index par nom d'objet
        $index1 = [];
        foreach ($objets1 as $objet) {
            $index1[$objet['nom']] = $objet;
        }

        $index2 = [];
        foreach ($objets2 as $objet) {
            $index2[$objet['nom']] = $objet;
        }

        // Objets ajoutés
        foreach ($objets2 as $objet) {
            if (!isset($index1[$objet['nom']])) {
                $differences['ajoutes'][] = $objet;
            }
        }

        // Objets supprimés
        foreach ($objets1 as $objet) {
            if (!isset($index2[$objet['nom']])) {
                $differences['supprimes'][] = $objet;
            }
        }

        // Objets modifiés
        foreach ($objets2 as $objet) {
            if (isset($index1[$objet['nom']])) {
                $ancien = $index1[$objet['nom']];

                if ($ancien['quantite'] != $objet['quantite'] ||
                    $ancien['etat'] != $objet['etat']) {
                    $differences['modifies'][] = [
                        'ancien' => $ancien,
                        'nouveau' => $objet,
                    ];
                }
            }
        }

        return $differences;
    }

    /**
     * Exporte l'inventaire au format PDF
     *
     * @param int $sessionId
     * @return string|false Chemin du fichier PDF
     */
    public function exporterPDF($sessionId)
    {
        $session = $this->getSession($sessionId);
        $objets = $this->getObjetsSession($sessionId);

        // Ici, utiliser une bibliothèque PDF comme TCPDF ou mPDF
        // Pour cet exemple, on crée un HTML simple

        $html = "<h1>Inventaire - {$session['logement_nom']}</h1>";
        $html .= "<p>Date : {$session['date_debut']}</p>";
        $html .= "<table border='1'>";
        $html .= "<tr><th>Nom</th><th>Quantité</th><th>Marque</th><th>État</th><th>Valeur</th></tr>";

        foreach ($objets as $objet) {
            $html .= "<tr>";
            $html .= "<td>{$objet['nom']}</td>";
            $html .= "<td>{$objet['quantite']}</td>";
            $html .= "<td>{$objet['marque']}</td>";
            $html .= "<td>{$objet['etat']}</td>";
            $html .= "<td>{$objet['valeur']} €</td>";
            $html .= "</tr>";
        }

        $html .= "</table>";

        $filename = "inventaire_session_{$sessionId}.html";
        $filepath = BASE_PATH . "/generated_contracts/" . $filename;

        file_put_contents($filepath, $html);

        return $filepath;
    }
}
