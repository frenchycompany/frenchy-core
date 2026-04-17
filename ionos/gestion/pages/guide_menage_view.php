<?php
require_once __DIR__ . '/../db/connection.php';
$id = (int)($_GET['id'] ?? 0);
if (!$id) { echo 'Guide non trouvé'; exit; }
$guide = $conn->prepare("SELECT * FROM guide_menage_guides WHERE id=?");
$guide->execute([$id]); $guide = $guide->fetch(PDO::FETCH_ASSOC);
if (!$guide) { echo 'Guide non trouvé'; exit; }
$zones = $conn->prepare("SELECT * FROM guide_menage_zones WHERE id_guide=? ORDER BY ordre");
$zones->execute([$id]); $zones = $zones->fetchAll(PDO::FETCH_ASSOC);
$taches = [];
$t = $conn->prepare("SELECT t.* FROM guide_menage_taches t JOIN guide_menage_zones z ON t.id_zone=z.id WHERE z.id_guide=? ORDER BY t.ordre");
$t->execute([$id]);
foreach ($t->fetchAll(PDO::FETCH_ASSOC) as $row) $taches[$row['id_zone']][$row['section']][] = $row;
$sections = ['etat'=>['État général','fa-eye'],'nettoyage'=>['Nettoyage détaillé','fa-spray-can-sparkles'],'mise_en_place'=>['Mise en place','fa-hand-sparkles'],'equipements'=>['Contrôle équipements','fa-plug']];
$regles = array_filter(explode("\n", $guide['regles_generales'] ?? ''));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Guide Ménage – <?= htmlspecialchars($guide['nom']) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#f5f5f0;color:#222;line-height:1.5}
.header{background:linear-gradient(135deg,#1a1a1a,#2d2d2d);color:#fff;padding:2rem 1rem;text-align:center}
.header h1{font-size:1.6rem;font-weight:300;letter-spacing:3px;text-transform:uppercase}
.header .gold{color:#c9a84c;font-size:1.3rem;letter-spacing:4px;margin:.5rem 0;font-weight:600}
.header .sub{font-size:.85rem;color:#999;letter-spacing:1px}
.nav-z{background:#fff;border-bottom:1px solid #e0e0e0;padding:.5rem;display:flex;flex-wrap:wrap;gap:.4rem;justify-content:center;position:sticky;top:0;z-index:100;box-shadow:0 2px 8px rgba(0,0,0,.08)}
.nav-z a{font-size:.7rem;padding:.3rem .6rem;border-radius:20px;background:#f0f0f0;color:#333;text-decoration:none;white-space:nowrap;transition:all .2s}
.nav-z a:hover{background:#1a1a1a;color:#c9a84c}
.rules{background:#fff;margin:1rem;padding:1.5rem;border-radius:12px;border-left:4px solid #c9a84c;box-shadow:0 2px 8px rgba(0,0,0,.05)}
.rules h2{font-size:1rem;margin-bottom:.8rem;display:flex;align-items:center;gap:.5rem}
.rules li{padding:.3rem 0;padding-left:1.5rem;position:relative;font-size:.9rem;list-style:none}
.rules li::before{content:'✦';position:absolute;left:0;color:#c9a84c}
.zone{background:#fff;margin:1rem;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.05)}
.zh{display:flex;align-items:center;padding:1rem 1.2rem;cursor:pointer;background:linear-gradient(135deg,#1a1a1a,#2d2d2d);color:#fff;gap:.8rem;user-select:none}
.zh i.ic{color:#c9a84c;font-size:1.2rem;width:30px;text-align:center}
.zh h3{flex:1;font-size:1rem;font-weight:400;letter-spacing:1px}
.zh .chev{transition:transform .3s;color:#c9a84c}
.zh.open .chev{transform:rotate(180deg)}
.zb{display:none;padding:1rem 1.2rem}
.zh.open+.zb{display:block}
.stit{font-size:.85rem;text-transform:uppercase;letter-spacing:1px;color:#c9a84c;border-bottom:1px solid #eee;padding-bottom:.4rem;margin:.8rem 0 .5rem;display:flex;align-items:center;gap:.5rem}
.task{display:flex;align-items:flex-start;gap:.6rem;padding:.5rem 0;border-bottom:1px solid #f5f5f5}
.task:last-child{border-bottom:none}
.task input[type=checkbox]{width:20px;height:20px;accent-color:#c9a84c;flex-shrink:0;margin-top:2px;cursor:pointer}
.task label{font-size:.9rem;cursor:pointer;flex:1}
.task .note{font-size:.75rem;color:#888;font-style:italic;display:block;margin-top:2px}
.task-photo{max-width:100%;border-radius:8px;margin-top:.5rem}
.ref-photo{max-width:100%;border-radius:8px;margin:.8rem 0}
.pbar{background:#eee;height:6px;border-radius:3px;margin:.5rem 0;overflow:hidden}
.pbar .fill{background:linear-gradient(90deg,#c9a84c,#e0c068);height:100%;width:0%;transition:width .3s;border-radius:3px}
.zprog{font-size:.75rem;color:#999}
.footer{text-align:center;padding:2rem 1rem;color:#999;font-size:.75rem}
.btn-top{position:fixed;bottom:20px;right:20px;background:#1a1a1a;color:#c9a84c;border:none;width:44px;height:44px;border-radius:50%;font-size:1.2rem;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,.2);display:none;z-index:200}
.btn-print{position:fixed;bottom:20px;left:20px;background:#c9a84c;color:#1a1a1a;border:none;width:44px;height:44px;border-radius:50%;font-size:1.2rem;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,.2);z-index:200}
@media print{.nav-z,.btn-top,.btn-print{display:none!important}.zb{display:block!important}.zone{break-inside:avoid;margin:.5rem 0}}
@media(min-width:768px){.header h1{font-size:2rem}body{max-width:900px;margin:0 auto}}
</style>
</head>
<body>
<div class="header">
<h1>Guide Ménage</h1>
<div class="gold"><?= htmlspecialchars($guide['nom']) ?></div>
<div class="sub"><?= htmlspecialchars($guide['sous_titre']) ?></div>
</div>

<nav class="nav-z">
<?php if ($regles): ?><a href="#regles">Règles</a><?php endif; ?>
<?php foreach ($zones as $i=>$z): ?>
<a href="#zone_<?= $z['id'] ?>"><?= htmlspecialchars($z['nom']) ?></a>
<?php endforeach; ?>
</nav>

<?php if ($regles): ?>
<section class="rules" id="regles">
<h2><i class="fas fa-scroll" style="color:#c9a84c"></i> Règles Générales</h2>
<ul><?php foreach ($regles as $r): ?><li><?= htmlspecialchars(trim($r)) ?></li><?php endforeach; ?></ul>
</section>
<?php endif; ?>

<?php foreach ($zones as $z): $zt = $taches[$z['id']] ?? []; ?>
<div class="zone" id="zone_<?= $z['id'] ?>">
<div class="zh" onclick="this.classList.toggle('open')">
<i class="fas <?= htmlspecialchars($z['icon']) ?> ic"></i>
<h3><?= htmlspecialchars($z['nom']) ?></h3>
<span class="zprog"><span class="cnt">0/0</span></span>
<i class="fas fa-chevron-down chev"></i>
</div>
<div class="zb">
<div class="pbar"><div class="fill"></div></div>
<?php foreach ($sections as $sk=>[$sl,$si]): if (empty($zt[$sk])) continue; ?>
<div class="stit"><i class="fas <?= $si ?>"></i> <?= $sl ?></div>
<?php foreach ($zt[$sk] as $t): ?>
<div class="task">
<input type="checkbox" id="t<?= $t['id'] ?>">
<label for="t<?= $t['id'] ?>"><?= htmlspecialchars($t['texte']) ?>
<?php if ($t['note']): ?><span class="note"><?= htmlspecialchars($t['note']) ?></span><?php endif; ?>
<?php if ($t['photo']): ?><img src="../<?= htmlspecialchars($t['photo']) ?>" class="task-photo"><?php endif; ?>
</label>
</div>
<?php endforeach; endforeach; ?>
<?php if ($z['photo_reference']): ?>
<div class="stit"><i class="fas fa-camera"></i> Références visuelles</div>
<img src="../<?= htmlspecialchars($z['photo_reference']) ?>" class="ref-photo">
<?php endif; ?>
</div>
</div>
<?php endforeach; ?>

<div style="text-align:center;margin:2rem 1rem"><button onclick="if(confirm('Réinitialiser ?')){localStorage.removeItem('gm_<?= $id ?>');location.reload()}" style="background:#1a1a1a;color:#c9a84c;border:none;padding:.8rem 2rem;border-radius:8px;cursor:pointer"><i class="fas fa-rotate-left"></i> Réinitialiser</button></div>
<div class="footer"><?= htmlspecialchars($guide['nom']) ?> – Guide ménage<br>FrenchyConciergerie © 2026</div>
<button class="btn-top" id="btnTop" onclick="window.scrollTo({top:0,behavior:'smooth'})"><i class="fas fa-arrow-up"></i></button>
<button class="btn-print" onclick="window.print()"><i class="fas fa-print"></i></button>
<script>
function up(z){const b=z.querySelectorAll('input[type=checkbox]'),c=z.querySelectorAll('input[type=checkbox]:checked'),n=z.querySelector('.cnt'),f=z.querySelector('.fill');if(n)n.textContent=c.length+'/'+b.length;if(f)f.style.width=b.length?(c.length/b.length*100)+'%':'0%'}
document.querySelectorAll('input[type=checkbox]').forEach(cb=>cb.addEventListener('change',function(){up(this.closest('.zone'));save()}));
document.querySelectorAll('.zone').forEach(z=>up(z));
window.addEventListener('scroll',()=>document.getElementById('btnTop').style.display=window.scrollY>300?'block':'none');
document.querySelectorAll('.nav-z a').forEach(a=>a.addEventListener('click',function(){const t=document.querySelector(this.getAttribute('href'));if(t){const h=t.querySelector('.zh');if(h&&!h.classList.contains('open'))h.classList.add('open');const b=t.querySelector('.zb');if(b)b.style.display='block';up(t)}}));
const SK='gm_<?= $id ?>';
function save(){const s={};document.querySelectorAll('input[type=checkbox]').forEach(c=>s[c.id]=c.checked);localStorage.setItem(SK,JSON.stringify(s))}
try{const s=JSON.parse(localStorage.getItem(SK));if(s)Object.keys(s).forEach(id=>{const c=document.getElementById(id);if(c)c.checked=s[id]});document.querySelectorAll('.zone').forEach(z=>up(z))}catch(e){}
</script>
</body></html>
