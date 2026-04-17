<?php
require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../includes/auth.php';

$upload_dir = __DIR__ . '/../uploads/guide_menage/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

// --- AJAX actions ---
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $action = $_POST['ajax_action'];
    try {
        if ($action === 'create_guide') {
            $stmt = $conn->prepare("INSERT INTO guide_menage_guides (nom, sous_titre) VALUES (?, '')");
            $stmt->execute([$_POST['nom']]);
            echo json_encode(['ok'=>true, 'id'=>$conn->lastInsertId()]);
        }
        elseif ($action === 'delete_guide') {
            $conn->prepare("DELETE FROM guide_menage_guides WHERE id=?")->execute([$_POST['id']]);
            echo json_encode(['ok'=>true]);
        }
        elseif ($action === 'save_guide') {
            $conn->prepare("UPDATE guide_menage_guides SET nom=?, sous_titre=?, regles_generales=? WHERE id=?")
                ->execute([$_POST['nom'], $_POST['sous_titre'], $_POST['regles'], $_POST['id']]);
            echo json_encode(['ok'=>true]);
        }
        elseif ($action === 'add_zone') {
            $max = $conn->query("SELECT COALESCE(MAX(ordre),0)+1 FROM guide_menage_zones WHERE id_guide=".(int)$_POST['id_guide'])->fetchColumn();
            $stmt = $conn->prepare("INSERT INTO guide_menage_zones (id_guide, nom, icon, ordre) VALUES (?,?,?,?)");
            $stmt->execute([$_POST['id_guide'], $_POST['nom'], $_POST['icon'] ?: 'fa-door-open', $max]);
            echo json_encode(['ok'=>true, 'id'=>$conn->lastInsertId()]);
        }
        elseif ($action === 'save_zone') {
            $conn->prepare("UPDATE guide_menage_zones SET nom=?, icon=? WHERE id=?")->execute([$_POST['nom'], $_POST['icon'], $_POST['id']]);
            echo json_encode(['ok'=>true]);
        }
        elseif ($action === 'delete_zone') {
            $conn->prepare("DELETE FROM guide_menage_zones WHERE id=?")->execute([$_POST['id']]);
            echo json_encode(['ok'=>true]);
        }
        elseif ($action === 'reorder_zones') {
            $ids = json_decode($_POST['order'], true);
            foreach ($ids as $i => $id) {
                $conn->prepare("UPDATE guide_menage_zones SET ordre=? WHERE id=?")->execute([$i, $id]);
            }
            echo json_encode(['ok'=>true]);
        }
        elseif ($action === 'add_tache') {
            $max = $conn->query("SELECT COALESCE(MAX(ordre),0)+1 FROM guide_menage_taches WHERE id_zone=".(int)$_POST['id_zone'])->fetchColumn();
            $stmt = $conn->prepare("INSERT INTO guide_menage_taches (id_zone, section, texte, note, ordre) VALUES (?,?,?,?,?)");
            $stmt->execute([$_POST['id_zone'], $_POST['section'], $_POST['texte'], $_POST['note'] ?? '', $max]);
            echo json_encode(['ok'=>true, 'id'=>$conn->lastInsertId()]);
        }
        elseif ($action === 'save_tache') {
            $conn->prepare("UPDATE guide_menage_taches SET texte=?, note=?, section=? WHERE id=?")->execute([$_POST['texte'], $_POST['note'] ?? '', $_POST['section'], $_POST['id']]);
            echo json_encode(['ok'=>true]);
        }
        elseif ($action === 'delete_tache') {
            $conn->prepare("DELETE FROM guide_menage_taches WHERE id=?")->execute([$_POST['id']]);
            echo json_encode(['ok'=>true]);
        }
        elseif ($action === 'upload_photo') {
            if (!empty($_FILES['photo']['tmp_name'])) {
                $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png','webp'])) { echo json_encode(['ok'=>false,'error'=>'Format non supporté']); exit; }
                $fname = uniqid('gm_').'.'.$ext;
                move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir.$fname);
                $path = 'uploads/guide_menage/'.$fname;
                if ($_POST['type'] === 'zone') {
                    $conn->prepare("UPDATE guide_menage_zones SET photo_reference=? WHERE id=?")->execute([$path, $_POST['id']]);
                } else {
                    $conn->prepare("UPDATE guide_menage_taches SET photo=? WHERE id=?")->execute([$path, $_POST['id']]);
                }
                echo json_encode(['ok'=>true, 'path'=>$path]);
            } else { echo json_encode(['ok'=>false]); }
        }
        elseif ($action === 'delete_photo') {
            if ($_POST['type'] === 'zone') {
                $old = $conn->query("SELECT photo_reference FROM guide_menage_zones WHERE id=".(int)$_POST['id'])->fetchColumn();
                $conn->prepare("UPDATE guide_menage_zones SET photo_reference=NULL WHERE id=?")->execute([$_POST['id']]);
            } else {
                $old = $conn->query("SELECT photo FROM guide_menage_taches WHERE id=".(int)$_POST['id'])->fetchColumn();
                $conn->prepare("UPDATE guide_menage_taches SET photo=NULL WHERE id=?")->execute([$_POST['id']]);
            }
            if ($old && file_exists(__DIR__.'/../'.$old)) @unlink(__DIR__.'/../'.$old);
            echo json_encode(['ok'=>true]);
        }
    } catch (Exception $e) { echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]); }
    exit;
}

// --- Page data ---
$guides = $conn->query("SELECT * FROM guide_menage_guides ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
$edit_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$guide = null; $zones = []; $taches = [];
if ($edit_id) {
    $guide = $conn->prepare("SELECT * FROM guide_menage_guides WHERE id=?");
    $guide->execute([$edit_id]); $guide = $guide->fetch(PDO::FETCH_ASSOC);
    if ($guide) {
        $zones = $conn->prepare("SELECT * FROM guide_menage_zones WHERE id_guide=? ORDER BY ordre");
        $zones->execute([$edit_id]); $zones = $zones->fetchAll(PDO::FETCH_ASSOC);
        $t = $conn->prepare("SELECT t.* FROM guide_menage_taches t JOIN guide_menage_zones z ON t.id_zone=z.id WHERE z.id_guide=? ORDER BY t.ordre");
        $t->execute([$edit_id]); 
        foreach ($t->fetchAll(PDO::FETCH_ASSOC) as $row) $taches[$row['id_zone']][] = $row;
    }
}
$section_labels = ['etat'=>'État général','nettoyage'=>'Nettoyage détaillé','mise_en_place'=>'Mise en place','equipements'=>'Contrôle équipements'];
$section_icons = ['etat'=>'fa-eye','nettoyage'=>'fa-spray-can-sparkles','mise_en_place'=>'fa-hand-sparkles','equipements'=>'fa-plug'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin – Guides Ménage</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
.zone-card{border:1px solid #dee2e6;border-radius:8px;margin-bottom:1rem}
.zone-card-header{background:#1a1a1a;color:#fff;padding:.8rem 1rem;border-radius:8px 8px 0 0;cursor:pointer;display:flex;align-items:center;gap:.5rem}
.zone-card-header .gold{color:#c9a84c}
.zone-card-body{padding:1rem;display:none}
.zone-card-header.open+.zone-card-body{display:block}
.section-title{font-size:.85rem;font-weight:600;color:#c9a84c;border-bottom:1px solid #eee;padding-bottom:.3rem;margin:1rem 0 .5rem}
.tache-row{display:flex;align-items:center;gap:.5rem;padding:.4rem 0;border-bottom:1px solid #f5f5f5}
.tache-row .texte{flex:1}
.tache-row .badge{font-size:.7rem}
.photo-thumb{width:60px;height:60px;object-fit:cover;border-radius:4px}
.btn-gold{background:#c9a84c;color:#fff;border:none}.btn-gold:hover{background:#b8923e;color:#fff}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/nav.php'; ?>
<div class="container-fluid py-3">

<?php if (!$guide): ?>
<!-- LISTE DES GUIDES -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="fas fa-broom text-warning"></i> Guides Ménage</h4>
    <button class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#modalNewGuide"><i class="fas fa-plus"></i> Nouveau guide</button>
</div>
<div class="row g-3">
<?php foreach ($guides as $g): ?>
<div class="col-md-4">
    <div class="card h-100">
        <div class="card-body">
            <h5><?= htmlspecialchars($g['nom']) ?></h5>
            <p class="text-muted small"><?= htmlspecialchars($g['sous_titre']) ?></p>
            <?php $zc = $conn->query("SELECT COUNT(*) FROM guide_menage_zones WHERE id_guide=".$g['id'])->fetchColumn(); ?>
            <span class="badge bg-secondary"><?= $zc ?> zones</span>
        </div>
        <div class="card-footer d-flex gap-2">
            <a href="?id=<?= $g['id'] ?>" class="btn btn-sm btn-outline-primary flex-fill"><i class="fas fa-edit"></i> Éditer</a>
            <a href="guide_menage_view.php?id=<?= $g['id'] ?>" target="_blank" class="btn btn-sm btn-outline-success flex-fill"><i class="fas fa-eye"></i> Voir</a>
            <button class="btn btn-sm btn-outline-danger" onclick="deleteGuide(<?= $g['id'] ?>)"><i class="fas fa-trash"></i></button>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<!-- Modal nouveau guide -->
<div class="modal fade" id="modalNewGuide"><div class="modal-dialog"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title">Nouveau guide</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body"><input type="text" class="form-control" id="newGuideName" placeholder="Nom du logement"></div>
<div class="modal-footer"><button class="btn btn-gold" onclick="createGuide()">Créer</button></div>
</div></div></div>

<?php else: ?>
<!-- ÉDITEUR DE GUIDE -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <a href="guide_menage_admin.php" class="btn btn-sm btn-outline-secondary me-2"><i class="fas fa-arrow-left"></i></a>
        <strong class="fs-5"><?= htmlspecialchars($guide['nom']) ?></strong>
    </div>
    <div class="d-flex gap-2">
        <a href="guide_menage_view.php?id=<?= $edit_id ?>" target="_blank" class="btn btn-sm btn-outline-success"><i class="fas fa-eye"></i> Aperçu</a>
        <button class="btn btn-sm btn-gold" data-bs-toggle="modal" data-bs-target="#modalGuideSettings"><i class="fas fa-cog"></i> Paramètres</button>
    </div>
</div>

<!-- Settings modal -->
<div class="modal fade" id="modalGuideSettings"><div class="modal-dialog"><div class="modal-content">
<div class="modal-header"><h5>Paramètres du guide</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-3"><label class="form-label">Nom</label><input type="text" class="form-control" id="guideNom" value="<?= htmlspecialchars($guide['nom']) ?>"></div>
    <div class="mb-3"><label class="form-label">Sous-titre</label><input type="text" class="form-control" id="guideSousTitre" value="<?= htmlspecialchars($guide['sous_titre']) ?>"></div>
    <div class="mb-3"><label class="form-label">Règles générales (1 par ligne)</label><textarea class="form-control" rows="6" id="guideRegles"><?= htmlspecialchars($guide['regles_generales']) ?></textarea></div>
</div>
<div class="modal-footer"><button class="btn btn-gold" onclick="saveGuide()">Enregistrer</button></div>
</div></div></div>

<!-- Zones -->
<div class="d-flex justify-content-between align-items-center mb-2">
    <h6 class="mb-0">Zones (<?= count($zones) ?>)</h6>
    <button class="btn btn-sm btn-gold" data-bs-toggle="modal" data-bs-target="#modalNewZone"><i class="fas fa-plus"></i> Zone</button>
</div>

<?php foreach ($zones as $z): ?>
<div class="zone-card" data-zone-id="<?= $z['id'] ?>">
    <div class="zone-card-header" onclick="this.classList.toggle('open')">
        <i class="fas <?= htmlspecialchars($z['icon']) ?> gold"></i>
        <span class="flex-grow-1"><?= htmlspecialchars($z['nom']) ?></span>
        <span class="badge bg-secondary"><?= count($taches[$z['id']] ?? []) ?></span>
        <i class="fas fa-chevron-down gold"></i>
    </div>
    <div class="zone-card-body">
        <!-- Zone actions -->
        <div class="d-flex gap-2 mb-3">
            <input type="text" class="form-control form-control-sm" value="<?= htmlspecialchars($z['nom']) ?>" id="znom_<?= $z['id'] ?>">
            <input type="text" class="form-control form-control-sm" style="width:120px" value="<?= htmlspecialchars($z['icon']) ?>" id="zicon_<?= $z['id'] ?>" placeholder="fa-icon">
            <button class="btn btn-sm btn-outline-primary" onclick="saveZone(<?= $z['id'] ?>)"><i class="fas fa-save"></i></button>
            <button class="btn btn-sm btn-outline-danger" onclick="deleteZone(<?= $z['id'] ?>)"><i class="fas fa-trash"></i></button>
        </div>

        <!-- Photo zone -->
        <div class="mb-3">
            <label class="form-label small fw-bold"><i class="fas fa-camera text-warning"></i> Photo de référence</label>
            <?php if ($z['photo_reference']): ?>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <img src="../<?= htmlspecialchars($z['photo_reference']) ?>" class="photo-thumb">
                    <button class="btn btn-sm btn-outline-danger" onclick="deletePhoto('zone',<?= $z['id'] ?>)"><i class="fas fa-trash"></i></button>
                </div>
            <?php endif; ?>
            <input type="file" class="form-control form-control-sm" accept="image/*" onchange="uploadPhoto(this,'zone',<?= $z['id'] ?>)">
        </div>

        <!-- Tâches par section -->
        <?php foreach ($section_labels as $sec_key => $sec_label): ?>
        <div class="section-title"><i class="fas <?= $section_icons[$sec_key] ?>"></i> <?= $sec_label ?></div>
        <div id="tasks_<?= $z['id'] ?>_<?= $sec_key ?>">
        <?php foreach (($taches[$z['id']] ?? []) as $t): if ($t['section'] !== $sec_key) continue; ?>
            <div class="tache-row" id="tache_<?= $t['id'] ?>">
                <input type="text" class="form-control form-control-sm texte" value="<?= htmlspecialchars($t['texte']) ?>" id="tt_<?= $t['id'] ?>">
                <input type="text" class="form-control form-control-sm" style="width:150px" value="<?= htmlspecialchars($t['note'] ?? '') ?>" placeholder="Note" id="tn_<?= $t['id'] ?>">
                <?php if ($t['photo']): ?>
                    <img src="../<?= htmlspecialchars($t['photo']) ?>" class="photo-thumb">
                    <button class="btn btn-sm btn-outline-danger" onclick="deletePhoto('tache',<?= $t['id'] ?>)"><i class="fas fa-times"></i></button>
                <?php else: ?>
                    <input type="file" class="form-control form-control-sm" style="width:140px" accept="image/*" onchange="uploadPhoto(this,'tache',<?= $t['id'] ?>)">
                <?php endif; ?>
                <button class="btn btn-sm btn-outline-primary" onclick="saveTache(<?= $t['id'] ?>,'<?= $sec_key ?>')"><i class="fas fa-save"></i></button>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteTache(<?= $t['id'] ?>)"><i class="fas fa-trash"></i></button>
            </div>
        <?php endforeach; ?>
        </div>
        <div class="d-flex gap-2 mt-1 mb-2">
            <input type="text" class="form-control form-control-sm" placeholder="Nouvelle tâche..." id="newtask_<?= $z['id'] ?>_<?= $sec_key ?>">
            <button class="btn btn-sm btn-outline-success" onclick="addTache(<?= $z['id'] ?>,'<?= $sec_key ?>')"><i class="fas fa-plus"></i></button>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<!-- Modal nouvelle zone -->
<div class="modal fade" id="modalNewZone"><div class="modal-dialog"><div class="modal-content">
<div class="modal-header"><h5>Nouvelle zone</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-3"><label class="form-label">Nom</label><input type="text" class="form-control" id="newZoneName"></div>
    <div class="mb-3"><label class="form-label">Icône FA</label><input type="text" class="form-control" id="newZoneIcon" value="fa-door-open"></div>
</div>
<div class="modal-footer"><button class="btn btn-gold" onclick="addZone()">Ajouter</button></div>
</div></div></div>
<?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const guideId = <?= $edit_id ?: 'null' ?>;
function ajax(data, cb) {
    const fd = data instanceof FormData ? data : (() => { const f = new FormData(); Object.keys(data).forEach(k => f.append(k, data[k])); return f; })();
    fetch('guide_menage_admin.php', {method:'POST', body:fd}).then(r=>r.json()).then(cb).catch(e=>alert(e));
}
function createGuide(){ajax({ajax_action:'create_guide',nom:document.getElementById('newGuideName').value},r=>{if(r.ok)location.href='?id='+r.id})}
function deleteGuide(id){if(confirm('Supprimer ce guide ?'))ajax({ajax_action:'delete_guide',id},()=>location.reload())}
function saveGuide(){ajax({ajax_action:'save_guide',id:guideId,nom:document.getElementById('guideNom').value,sous_titre:document.getElementById('guideSousTitre').value,regles:document.getElementById('guideRegles').value},()=>{bootstrap.Modal.getInstance(document.getElementById('modalGuideSettings')).hide();location.reload()})}
function addZone(){ajax({ajax_action:'add_zone',id_guide:guideId,nom:document.getElementById('newZoneName').value,icon:document.getElementById('newZoneIcon').value},()=>location.reload())}
function saveZone(id){ajax({ajax_action:'save_zone',id,nom:document.getElementById('znom_'+id).value,icon:document.getElementById('zicon_'+id).value},()=>{location.reload()})}
function deleteZone(id){if(confirm('Supprimer cette zone et ses tâches ?'))ajax({ajax_action:'delete_zone',id},()=>location.reload())}
function addTache(zid,sec){const inp=document.getElementById('newtask_'+zid+'_'+sec);if(!inp.value)return;ajax({ajax_action:'add_tache',id_zone:zid,section:sec,texte:inp.value},()=>location.reload())}
function saveTache(id,sec){ajax({ajax_action:'save_tache',id,texte:document.getElementById('tt_'+id).value,note:document.getElementById('tn_'+id).value,section:sec},()=>{document.getElementById('tache_'+id).style.background='#e8f5e9';setTimeout(()=>document.getElementById('tache_'+id).style.background='',500)})}
function deleteTache(id){if(confirm('Supprimer ?'))ajax({ajax_action:'delete_tache',id},()=>document.getElementById('tache_'+id).remove())}
function uploadPhoto(input,type,id){const fd=new FormData();fd.append('ajax_action','upload_photo');fd.append('photo',input.files[0]);fd.append('type',type);fd.append('id',id);ajax(fd,()=>location.reload())}
function deletePhoto(type,id){ajax({ajax_action:'delete_photo',type,id},()=>location.reload())}
</script>
</body></html>
