<?php
/**
 * Plugin Spip-Bonux
 * Le plugin qui lave plus SPIP que SPIP
 * (c) 2008 Mathieu Marcillaud, Cedric Morin, Romy Tetue
 * Licence GPL
 *
 */


/**
 * Empile un element dans un tableau declare par #SET{tableau,#ARRAY}
 * #SET_PUSH{tableau,valeur}
 *
 * @param object $p : objet balise
 * @return ""
**/
function balise_SET_PUSH_dist($p){
	$_nom = interprete_argument_balise(1,$p);
	$_valeur = interprete_argument_balise(2,$p);

	if ($_nom AND $_valeur)
		// si le tableau n'existe pas encore, on le cree
		// on ajoute la valeur ensuite (sans passer par array_push)
		$p->code = "vide((\$cle=$_nom)
			. (is_array(\$Pile['vars'][\$cle])?'':\$Pile['vars'][\$cle]=array())
			. (\$Pile['vars'][\$cle][]=$_valeur))";
	else
		$p->code = "''";

	$p->interdire_scripts = false; // la balise ne renvoie rien
	return $p;
}

/**
 * Si 3 arguments : Cree un tableau nom_tableau de t1 + t2
 * #SET_MERGE{nom_tableau,t1,t2}
 * #SET_MERGE{nom_tableau,#GET{tableau},#ARRAY{cle,valeur}}
 *
 * Si 2 arguments : Merge t1 dans nom_tableau
 * #SET_MERGE{nom_tableau,t1}
 * #SET_MERGE{nom_tableau,#GET{tableau}}
 *
 * @param object $p : objet balise
 * @return ""
**/
function balise_SET_MERGE_dist($p){
	$_nom = interprete_argument_balise(1,$p);
	$_t1 = interprete_argument_balise(2,$p);
	$_t2 = interprete_argument_balise(3,$p);

	if ($_nom AND $_t1 AND !$_t2)
		// 2 arguments : merge de $_nom et $_t1 dans $_nom
		// si le tableau n'existe pas encore, on le cree
		$p->code = "vide((\$cle=$_nom)
			. (is_array(\$Pile['vars'][\$cle])?'':\$Pile['vars'][\$cle]=array())
			. (is_array(\$new=$_t1)?'':\$new=array(\$new))
			. (\$Pile['vars'][\$cle] = array_merge(\$Pile['vars'][\$cle],\$new)))";
	elseif ($_nom AND $_t1 AND $_t2)
		// 3 arguments : merge de $_t1 et $_t2 dans $_nom
		// si le tableau n'existe pas encore, on le cree
		$p->code = "vide((\$cle=$_nom)
			. (is_array(\$Pile['vars'][\$cle])?'':\$Pile['vars'][\$cle]=array())
			. (is_array(\$new1=$_t1)?'':\$new1=array(\$new1))
			. (is_array(\$new2=$_t2)?'':\$new2=array(\$new2))
			. (\$Pile['vars'][\$cle] = array_merge(\$new1,\$new2)))";
	else
		$p->code = "''";

	$p->interdire_scripts = false; // la balise ne renvoie rien
	return $p;
}

/**
 * Balise #COMPTEUR associee au critere compteur
 *
 * @param unknown_type $p
 * @return unknown
 */
function balise_COMPTEUR_dist($p) {
	calculer_balise_criteres('compteur', $p);
	if ($p->code=="''")
		calculer_balise_criteres('compteur', $p, "compteur_left");
	return $p;
}

/** Balise #SOMME associee au critere somme */
function balise_SOMME_dist($p) {
	return calculer_balise_criteres('somme', $p);
}

/** Balise #COMPTE associee au critere compte */
function balise_COMPTE_dist($p) {
	return calculer_balise_criteres('compte', $p);
}

/** Balise #MOYENNE associee au critere moyenne */
function balise_MOYENNE_dist($p) {
	return calculer_balise_criteres('moyenne', $p);
}

/** Balise #MINIMUM associee au critere moyenne */
function balise_MINIMUM_dist($p) {
	return calculer_balise_criteres('minimum', $p);
}

/** Balise #MAXIMUM associee au critere moyenne */
function balise_MAXIMUM_dist($p) {
	return calculer_balise_criteres('maximum', $p);
}

/** Balise #STATS associee au critere stats
 * #STATS{id_article,moyenne}
 */
function balise_STATS_dist($p) {
	if (isset($p->param[0][2][0])
	AND $nom = ($p->param[0][2][0]->texte)) {
		return calculer_balise_criteres($nom, $p, 'stats');
	}
	return $p;
}

function calculer_balise_criteres($nom, $p, $motif="") {
	$p->code = "''";
	$motif = $motif ? $motif : $nom;
	if (isset($p->param[0][1][0])
	AND $champ = ($p->param[0][1][0]->texte)) {
		return rindex_pile($p, $nom."_$champ", $motif);
	}
  return $p;
}

/**
 * Produire un fichier statique a partir d'un squelette dynamique
 * Permet ensuite a apache de le servir en statique sans repasser
 * par spip.php a chaque hit sur le fichier
 * le format css ou js doit etre passe dans options['format']
 *
 * @param string $fond
 * @param array $contexte
 * @param array $options
 * @param string $connect
 * @return string
 */
function produire_fond_statique($fond, $contexte=array(), $options = array(), $connect=''){
	// recuperer le code CSS produit par le squelette
	$options['raw'] = true;
	$cache = recuperer_fond($fond,$contexte,$options,$connect);
  $extension = $options['format'];

  // calculer le nom de la css
	$dir_var = sous_repertoire (_DIR_VAR, 'cache-'.$extension);
	$filename = $dir_var . $extension."dyn-".md5($fond.serialize($contexte).$connect) .".$extension";

  if (!file_exists($filename)
	  OR filemtime($filename)<$cache['lastmodified']){

	  $contenu = $cache['texte'];
	  // passer les urls en absolu si c'est une css
	  if ($extension=="css")
	    $contenu = urls_absolues_css($contenu, generer_url_public($fond));

    $comment = "/*\n * #PRODUIRE_".strtoupper($extension)."_FOND{fond=$fond";
    foreach($contexte as $k=>$v)
	    $comment .= ",$k=$v";
    $comment .="}\n * le ".date("Y-m-d H:i:s")."\n */\n";
	  // et ecrire le fichier
    ecrire_fichier($filename,$comment.$contenu);
  }

  return $filename;
}

function produire_css_fond($fond, $contexte=array(), $options = array(), $connect=''){
	$options['format'] = "css";
  return produire_fond_statique($fond, $contexte, $options, $connect);
}
function produire_js_fond($fond, $contexte=array(), $options = array(), $connect=''){
	$options['format'] = "js";
  return produire_fond_statique($fond, $contexte, $options, $connect);
}

/**
 * #PRODUIRE_CSS_FOND
 * generer un fichier css statique a partir d'un squelette de CSS
 * utilisable en
 *
 * <link rel="stylesheet" type="text/css" href="#PRODUIRE_CSS_FOND{fond=css/macss,couleur=ffffff}" />
 * la syntaxe de la balise est la meme que celle de #INCLURE
 *
 * @param object $p
 * @return object
 */
function balise_PRODUIRE_CSS_FOND_dist($p){
	$balise_inclure = charger_fonction('INCLURE','balise');
	$p = $balise_inclure($p);

	$p->code = str_replace('recuperer_fond(','produire_css_fond(',$p->code);

	return $p;
}
/**
 * #PRODUIRE_JS_FOND
 * generer un fichier js statique a partir d'un squelette de JS
 * utilisable en
 *
 * <script type="text/javascript" src="#PRODUIRE_JS_FOND{fond=js/monscript}" ></script>
 * la syntaxe de la balise est la meme que celle de #INCLURE
 *
 * @param object $p
 * @return object
 */
function balise_PRODUIRE_JS_FOND_dist($p){
	$balise_inclure = charger_fonction('INCLURE','balise');
	$p = $balise_inclure($p);

	$p->code = str_replace('recuperer_fond(','produire_js_fond(',$p->code);

	return $p;
}

?>
