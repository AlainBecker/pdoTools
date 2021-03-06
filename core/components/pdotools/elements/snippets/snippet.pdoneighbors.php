<?php
/* @var array $scriptProperties */
/* @var pdoFetch $pdoFetch */
if (!$modx->getService('pdoFetch')) {return false;}
$pdoFetch = new pdoFetch($modx, $scriptProperties);
$pdoFetch->addTime('pdoTools loaded');

if (empty($id)) {$id = $modx->resource->id;}
if (empty($limit)) {$limit = 1;}
if (empty($scheme)) {$scheme = $modx->getOption('link_tag_scheme');}
if (!isset($outputSeparator)) {$outputSeparator = "\n";}
$fastMode = !empty($fastMode);

$class = 'modResource';
$resource = ($id == $modx->resource->id)
	? $modx->resource
	: $modx->getObject($class, $id);
if (!$resource) {return '';}

$where = array(
	array(
		$class.'.parent' => $resource->parent,
		'OR:'.$class.'.id:=' => $resource->parent,
	)
);

// Fields to select
$resourceColumns = array_keys($modx->getFieldMeta($class));
if (empty($includeContent) && empty($useWeblinkUrl)) {
	$key = array_search('content', $resourceColumns);
	unset($resourceColumns[$key]);
}
$select = array($class => implode(',',$resourceColumns));

// Add custom parameters
foreach (array('where','select') as $v) {
	if (!empty($scriptProperties[$v])) {
		$tmp = $modx->fromJSON($scriptProperties[$v]);
		if (is_array($tmp)) {
			$$v = array_merge($$v, $tmp);
		}
	}
	unset($scriptProperties[$v]);
}
$pdoFetch->addTime('Conditions prepared');

// Default parameters
$default = array(
	'class' => $class,
	'where' => $modx->toJSON($where),
	'select' => $modx->toJSON($select),
	//'groupby' => $class.'.id',
	'sortby' => $class.'.menuindex',
	'sortdir' => 'ASC',
	'return' => 'data',
	'limit' => 0,
	'totalVar' => 'pdoneighbors.total',
);

// Merge all properties and run!
unset($scriptProperties['limit']);
$pdoFetch->addTime('Query parameters ready');
$pdoFetch->setConfig(array_merge($default, $scriptProperties), false);

$rows = $pdoFetch->run();
$output = array(
	'prev' => array(),
	'up' => '',
	'next' => array(),
);

$found = false;
$prev = $next = array();
if (!empty($rows)) {
	foreach ($rows as $row) {
		if (empty($row['menutitle'])) {$row['menutitle'] = $row['pagetitle'];}
		if (!empty($useWeblinkUrl) && $row['class_key'] == 'modWebLink' || $row['class_key'] == 'modSymLink') {
			$row['link'] = is_numeric(trim($row['content'], '[]~ '))
				? $modx->makeUrl(intval(trim($row['content'], '[]~ ')), $row['context_key'], '', $scheme)
				: $row['content'];
		}
		else {
			$row['link'] = $modx->makeUrl($row['id'], $row['context_key'], '', $scheme);
		}

		if ($row['id'] == $resource->id) {
			$found = true;
		}
		elseif ($row['id'] == $resource->parent) {
			$output['up'] = !empty($tplUp)
				? $pdoFetch->getChunk($tplUp, $row, $fastMode)
				: $pdoFetch->getChunk('', $row);
		}
		elseif ($found) {
			$next[] = $row;
		}
		else {
			$prev[] = $row;
		}
	}
}

while (count($output['prev']) < $limit && !empty($prev)) {
	$output['prev'][] = !empty($tplPrev)
		? $pdoFetch->getChunk($tplPrev, array_pop($prev), $fastMode)
		: $pdoFetch->getChunk('', array_pop($prev));
}
while (count($output['next']) < $limit && !empty($next)) {
	$output['next'][] = !empty($tplNext)
		? $pdoFetch->getChunk($tplNext, array_shift($next), $fastMode)
		: $pdoFetch->getChunk('', array_shift($next));
}
$pdoFetch->addTime('Chunks processed');

$log = '';
if ($modx->user->hasSessionContext('mgr') && !empty($showLog)) {
	$log .= '<pre class="pdoNeighborsLog">' . print_r($pdoFetch->getTime(), 1) . '</pre>';
}
$output['prev'] = implode($outputSeparator, $output['prev']);
$output['next'] = implode($outputSeparator, $output['next']);

if (!empty($toSeparatePlaceholders)) {
	$output['log'] = $log;
	$modx->setPlaceholders($output, $toSeparatePlaceholders);
}
else {
	$output = !empty($tplWrapper)
		? $pdoFetch->getChunk($tplWrapper, $output, $fastMode)
		: $pdoFetch->getChunk('', $output);
	$output .= $log;

	if (!empty($toPlaceholder)) {
		$modx->setPlaceholder($toPlaceholder, $output);
	}
	else {
		return $output;
	}
}