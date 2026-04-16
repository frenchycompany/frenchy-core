-- ============================================
-- Contrat de location saisonnière — Template Vertefeuille / B2B
-- Ajout des champs locataire société + insertion du template
-- ============================================

-- 1. Nouveaux champs pour contrats B2B (locataire = société)
INSERT IGNORE INTO location_contract_fields (field_name, description, input_type, field_group, sort_order) VALUES
('locataire_societe', 'Nom de la société locataire', 'text', 'voyageur', 52),
('locataire_forme_juridique', 'Forme juridique (SAS, SARL, etc.)', 'text', 'voyageur', 53),
('locataire_siege', 'Siège social du locataire', 'text', 'voyageur', 54),
('locataire_siret', 'SIRET du locataire', 'text', 'voyageur', 55),
('locataire_rcs_ville', 'Ville RCS du locataire', 'text', 'voyageur', 56),
('locataire_representant', 'Représentant légal du locataire', 'text', 'voyageur', 57),
('proprietaire_representant', 'Représentant légal du propriétaire', 'text', 'proprietaire', 585),
('proprietaire_forme_juridique', 'Forme juridique (SAS, SARL, etc.)', 'text', 'proprietaire', 582),
('proprietaire_rcs_ville', 'Ville RCS du propriétaire', 'text', 'proprietaire', 592),
('duree_location', 'Durée de la location (jours)', 'number', 'reservation', 125),
('prix_total_lettres', 'Prix total en lettres', 'text', 'reservation', 155),
('prix_total_ht', 'Prix total HT (EUR)', 'number', 'reservation', 152),
('modalite_paiement', 'Modalités de paiement', 'textarea', 'reservation', 185),
('date_limite_paiement', 'Date limite de paiement', 'date', 'reservation', 186);

-- 2. Template du contrat de location saisonnière
INSERT INTO location_contract_templates (title, content, placeholders) VALUES (
'Contrat location saisonnière — B2B (société)',
'<div style=\"font-family: Georgia, serif; max-width: 800px; margin: 0 auto; padding: 40px; line-height: 1.7; color: #222;\">

<h1 style=\"text-align: center; font-size: 22px; text-transform: uppercase; letter-spacing: 2px; border-bottom: 2px solid #333; padding-bottom: 15px;\">Contrat de Location Saisonnière</h1>

<!-- ===== ARTICLE 1 ===== -->
<h2 style=\"margin-top: 30px; font-size: 16px;\">ARTICLE 1 – DÉSIGNATION DES PARTIES</h2>
<p>Le présent contrat est conclu entre les soussignés :</p>

<h3 style=\"font-size: 14px; margin-top: 20px;\">BAILLEUR(S) :</h3>
<p>La Société \"{{proprietaire_societe}}\" {{proprietaire_forme_juridique}} dont le siège social est situé {{proprietaire_adresse_complete}}, immatriculée au registre du commerce et des sociétés de {{proprietaire_rcs_ville}} sous le numéro SIRET {{proprietaire_siret}}<br>
Représentée par {{proprietaire_representant}}</p>

<p>Ce dernier a donné procuration à la SAS Frenchy Conciergerie dont le siège social est 718 rue de la Louvière 60126 Longueil-Sainte-Marie immatriculée au registre du commerce et des sociétés de COMPIÈGNE sous le numéro SIRET 94499252800017<br>
Représentée par Raphaël Jacquet agissant en qualité de Directeur Général</p>

<p>D''une part, ci-après dénommé « LE(S) BAILLEUR(S) »</p>

<h3 style=\"font-size: 14px; margin-top: 20px;\">LOCATAIRE(S) :</h3>
<p>La Société {{locataire_societe}} {{locataire_forme_juridique}} dont le siège social est situé {{locataire_siege}}, immatriculée au registre du commerce et des sociétés de {{locataire_rcs_ville}} sous le numéro SIRET {{locataire_siret}}<br>
Représentée par {{locataire_representant}}</p>

<p>D''autre part, ci-après dénommé « LE(S) LOCATAIRE(S) »</p>


<!-- ===== ARTICLE 2 ===== -->
<h2 style=\"margin-top: 30px; font-size: 16px;\">ARTICLE 2 – OBJET DU CONTRAT DE LOCATION</h2>
<p>Le présent contrat a pour objet la location d''un logement ainsi déterminé :</p>

<h3 style=\"font-size: 14px;\">Article 2.1 – Désignation du bien</h3>
<p>Le bailleur donne en location, au profit du locataire, aux clauses et conditions ci-dessous énoncées, les locaux ci-après désignés :</p>
<ul style=\"list-style: none; padding-left: 0;\">
    <li>- <strong>Adresse du logement :</strong> {{adresse_logement}}</li>
    <li>- <strong>Type d''habitat :</strong> {{type_logement}}</li>
    <li>- <strong>Surface habitable :</strong> {{surface_m2}} m²</li>
    <li>- <strong>Désignation :</strong> {{description_logement}}</li>
</ul>
<p>- <strong>Locaux, parties, équipements et accessoires :</strong> {{equipements}}</p>
<p>Du tout tel que ledit bien se poursuit et se comporte, avec toutes ses aisances, dépendances et immeubles par destination, servitudes et mitoyennetés, sans exception ni réserve.</p>

<h3 style=\"font-size: 14px;\">Article 2.2 – Destination des lieux</h3>
<p>Les locaux objets de la location sont loués exclusivement à usage de LOCATION SAISONNIÈRE et n''ont pas pour objet la location d''un logement à usage de résidence principale ou à usage mixte résidentiel et professionnel pour le locataire.</p>
<p>Le présent contrat est donc hors champ d''application de la loi 89-462 du 06 juillet 1989.</p>


<!-- ===== ARTICLE 3 ===== -->
<h2 style=\"margin-top: 30px; font-size: 16px;\">ARTICLE 3 – DURÉE ET PRISE D''EFFET DE LA LOCATION</h2>
<p>La présente location est consentie pour une durée de <strong>{{duree_location}} jours</strong> à compter du <strong>{{date_arrivee}} à {{heure_arrivee}}</strong> pour se terminer le <strong>{{date_depart}} à {{heure_depart}}</strong>.</p>
<p>Ce contrat de location n''est pas renouvelable.</p>
<p>Le présent bail cesse de plein droit à l''expiration du délai précédemment indiqué sans qu''il soit nécessaire pour le bailleur de notifier un congé ou tout autre formalité. La durée du bail ne pourra être prorogée sans l''accord préalable du bailleur.</p>
<p>Il est rappelé que la durée maximale d''un contrat de location saisonnière est fixée à 90 jours par la loi n° 70-9 du 2 janvier 1970 réglementant les conditions d''exercice des activités relatives à certaines opérations portant sur les immeubles et les fonds de commerce, dite « loi Hoguet ».</p>


<!-- ===== ARTICLE 4 ===== -->
<h2 style=\"margin-top: 30px; font-size: 16px;\">ARTICLE 4 – CONDITIONS FINANCIÈRES DE LA LOCATION</h2>

<h3 style=\"font-size: 14px;\">Article 4.1 – Loyer et charges</h3>
<p>Le montant du loyer, pour l''intégralité de la durée de location, est fixé à la somme de <strong>{{prix_total_lettres}} euros {{prix_total_ht}} € HT</strong> charges comprises.</p>
<p>Le loyer ci-dessus comprend, pour toute la durée de la location, le paiement des charges locatives (chauffage, eau, électricité, taxe d''ordures ménagères, frais de ménage et conciergerie, etc.). Il comprend également le paiement des fournitures suivantes : internet, Netflix, etc.</p>
<p>Le locataire est redevable de la taxe de séjour pour la période de location prévue, qui s''élève à la somme de <strong>{{prix_taxe_sejour}} €</strong>.</p>

<h3 style=\"font-size: 14px;\">Article 4.2 – Réservation &amp; Modalités de paiement</h3>
<p>Le locataire s''engage à verser le montant de la location, soit la somme de {{prix_total_ht}} € HT, dans les conditions suivantes :</p>
<p>{{modalite_paiement}}</p>

<h3 style=\"font-size: 14px;\">Article 4.3 – Dépôt de garantie</h3>
<p>À titre de garantie le locataire versera, au plus tard le jour de l''entrée dans les lieux, la somme de <strong>{{depot_garantie}} €</strong>.</p>
<p>Le dépôt de garantie sera restitué au locataire dans un délai maximum de 10 jours après son départ et la remise des clés, déduction faite le cas échéant des sommes nécessaires pour couvrir les dégradations locatives ou tous dommages causés par le locataire.</p>


<!-- ===== ARTICLE 5 ===== -->
<h2 style=\"margin-top: 30px; font-size: 16px;\">ARTICLE 5 – CONDITIONS DE LA LOCATION</h2>
<p>La présente location est faite aux charges et conditions suivantes que le locataire s''oblige à exécuter et accomplir, à savoir :</p>
<ol>
    <li>Toute activité commerciale ou professionnelle est strictement interdite ;</li>
    <li>Respecter la capacité d''accueil de l''habitation telle que prévu par le présent contrat et le nombre d''occupants déclarés. L''organisation de rassemblements festifs est strictement prohibée ;</li>
    <li>Respecter la destination des lieux et à n''apporter aucune modification d''agencement des meubles ;</li>
    <li>Occuper personnellement les lieux. Il est interdit de sous-louer, en totalité ou partiellement, même gratuitement les lieux loués ;</li>
    <li>Être assuré contre les risques locatifs, vol, incendie, dégâts des eaux, etc. soit par son propre contrat d''assurance couvrant les risques de la location saisonnière, soit en souscrivant une police d''assurance particulière pour toute la durée de la location ;</li>
    <li>La présence d''animaux est strictement interdite, sauf accord exprès et écrit du bailleur ;</li>
    <li>Effectuer toute réclamation concernant le logement dans les 24 heures suivant l''entrée dans les lieux. Dans le cas contraire, elle ne pourra donner lieu à aucune compensation financière ou indemnisation ;</li>
    <li>Avertir le bailleur dans les plus brefs délais de la survenance de tout événement affectant l''habitation, son mobilier ou ses équipements. Les réparations rendues nécessaires par la négligence ou le mauvais entretien en cours de location, seront à la charge du locataire ;</li>
    <li>Autoriser le bailleur, ou toute personne mandatée par lui à cet effet, à effectuer, pendant la durée de la location, toute réparation nécessaire et urgente. Le Locataire ne pourra réclamer aucune réduction de loyer au cas où des réparations urgentes incombant au bailleur apparaîtraient en cours de location ;</li>
    <li>Il est interdit de fumer à l''intérieur du logement. Le ménage et/ou le nettoyage nécessaire en raison du mépris de cette clause sera facturé au locataire ;</li>
    <li>Accepter la visite des locaux si le bailleur ou son mandataire en font la demande.</li>
</ol>


<!-- ===== ARTICLE 6 ===== -->
<h2 style=\"margin-top: 30px; font-size: 16px;\">ARTICLE 6 – ÉTAT DES LIEUX ET INVENTAIRE</h2>
<p>Un état des lieux et un inventaire du mobilier seront établis entre le bailleur et le locataire au début et à la fin de la location en deux exemplaires pour chacune des parties.</p>
<p>En l''absence d''état des lieux de sortie et faute de contestation par le bailleur dans le délai de 48 heures suivant la fin de la location et la remise des clés, le logement sera présumé avoir été restitué en bon état de réparation locative.</p>
<p>Si la différence entre l''état des lieux d''entrée et l''état des lieux de sortie laisse apparaître des réparations ou dégradations à la charge du locataire, ces dernières seront retenues sur le dépôt de garantie. Si le dépôt de garantie ne suffit pas à couvrir les dépenses nécessaires, le bailleur sera en droit d''effectuer toute poursuite à l''encontre du locataire pour en obtenir le paiement ainsi que des dommages et intérêts.</p>


<!-- ===== ARTICLE 7 ===== -->
<h2 style=\"margin-top: 30px; font-size: 16px;\">ARTICLE 7 – ANNULATION</h2>
<p>Il est convenu entre les parties des conditions d''annulation suivantes :</p>
<ul>
    <li>En cas d''annulation du séjour par le locataire <strong>plus de deux mois</strong> avant la date prévue d''entrée dans les lieux, l''acompte versé sera remboursé.</li>
    <li>L''annulation du séjour par le locataire <strong>moins de deux mois et plus d''un mois</strong> avant la date prévue d''entrée dans les lieux entraîne la perte de l''acompte versé.</li>
    <li>L''annulation du séjour par le locataire <strong>moins d''un mois et plus de 15 jours</strong> avant la date prévue entraîne la perte de 50% de l''acompte versé.</li>
    <li>L''annulation du séjour par le bailleur entraîne, sous sept jours, remboursement au locataire de l''acompte déjà versé ou du montant total de la réservation si celle-ci a été entièrement payée.</li>
</ul>
<p>En cas de défaut d''entrée dans les lieux au plus tard 48 heures après la date de début de location, le bailleur se réserve le droit de chercher à relouer son bien pendant la période initialement réservée par le locataire.</p>


<!-- ===== ARTICLE 8 ===== -->
<h2 style=\"margin-top: 30px; font-size: 16px;\">ARTICLE 8 – CLAUSE DE SOLIDARITÉ</h2>
<p>En cas de pluralité de locataires, ces derniers reconnaissent être solidaires et indivis pour l''exécution de leurs obligations et notamment, sans que cette liste soit exhaustive, concernant le paiement du loyer, des charges et réparations locatives, d''éventuelles indemnités d''occupation ou de travaux de remise en état une fois le bail résilié.</p>


<!-- ===== ARTICLE 9 ===== -->
<h2 style=\"margin-top: 30px; font-size: 16px;\">ARTICLE 9 – CLAUSE RÉSOLUTOIRE &amp; CLAUSE PÉNALE</h2>
<p>Le présent contrat de location sera résilié immédiatement de plein droit, 48 heures après une simple notification par lettre recommandée avec accusé de réception ou lettre remise en main propre, dans les cas suivants :</p>
<ul>
    <li>À défaut de paiement aux termes de tout ou partie du loyer et des charges ;</li>
    <li>En cas de non versement du dépôt de garantie ;</li>
    <li>En cas d''inexécution des conditions du présent contrat.</li>
</ul>
<p>L''expulsion et la condamnation du locataire à des dommages et intérêts pourra être requise auprès du Tribunal du lieu de la situation de l''immeuble sans autre formalité préalable.</p>
<p>Il est également prévu qu''en cas de résiliation du présent contrat de location en raison des manquements du locataire à ses obligations et/ou en application de la clause résolutoire insérée au bail, une indemnité d''occupation conventionnelle d''occupation égale à <strong>TROIS fois le loyer quotidien</strong> sera en outre due jusqu''à libération complète et effective des lieux et la restitution des clés.</p>


<!-- ===== SIGNATURES ===== -->
<div style=\"margin-top: 50px; border-top: 1px solid #ccc; padding-top: 20px;\">
<p>Fait en autant d''exemplaires originaux que de parties, dont l''un a été remis aux locataires qui le reconnaissent, l''autre conservé par le bailleur.</p>

<p>Fait à {{lieu_signature}}<br>
Le {{date_contrat}}</p>

<div style=\"display: flex; justify-content: space-between; margin-top: 40px;\">
    <div style=\"width: 45%; text-align: center;\">
        <p><strong>Signature du locataire</strong></p>
        <div style=\"height: 80px; border-bottom: 1px dotted #999;\"></div>
    </div>
    <div style=\"width: 45%; text-align: center;\">
        <p><strong>Signature du bailleur</strong></p>
        <div style=\"height: 80px; border-bottom: 1px dotted #999;\"></div>
    </div>
</div>
</div>

</div>',
'proprietaire_societe,proprietaire_forme_juridique,proprietaire_adresse_complete,proprietaire_rcs_ville,proprietaire_siret,proprietaire_representant,locataire_societe,locataire_forme_juridique,locataire_siege,locataire_rcs_ville,locataire_siret,locataire_representant,adresse_logement,type_logement,surface_m2,description_logement,equipements,duree_location,date_arrivee,heure_arrivee,date_depart,heure_depart,prix_total_lettres,prix_total_ht,prix_taxe_sejour,modalite_paiement,depot_garantie,lieu_signature,date_contrat'
);
