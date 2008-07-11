<?

include INCLUDESPATH . 'easyparliament/member.php';

function api_getMP_front() {
?>
<p><big>Fetch a particular MP.</big></p>

<h4>Arguments</h4>
<dl>
<dt>postcode (optional)</dt>
<dd>Fetch the MP for a particular postcode (either the current one, or the most recent one, depending upon the setting of the always_return variable.</dd>
<dt>electorate (optional)</dt>
<dd>The name of a electorate; we will try and work it out from whatever you give us. :)</dd>
<dt>id (optional)</dt>
<dd>If you know the person ID for the member you want (returned from getMPs or elsewhere), this will return data for that person. <!-- <em>Also returns select committee membership and ministerial positions, past and present.</em> --></dd>
<dt>always_return (optional)</dt>
<dd>For the postcode and electorate options, sets whether to always try and return an MP, even if the seat is currently vacant.</dd>
<!-- 
<dt>extra (optional)</dt>
<dd>Returns extra data in one or more categories, separated by commas.</dd>
-->
</dl>

<h4>Example Response</h4>
<pre>&lt;twfy&gt;
  &lt;/twfy&gt;
</pre>

<?	
}

function _api_getMP_row($row) {
	global $parties;
	$row['full_name'] = member_full_name($row['house'], $row['title'], $row['first_name'],
		$row['last_name'], $row['electorate']);
	if (isset($parties[$row['party']]))
		$row['party'] = $parties[$row['party']];
	list($image,$sz) = find_rep_image($row['person_id']);
	if ($image) $row['image'] = $image;
	$row = array_map('html_entity_decode', $row);
	return $row;
}

function api_getMP_id($id) {
	$db = new ParlDB;
	$q = $db->query("select * from member
		where house=1 and person_id = '" . mysql_escape_string($id) . "'
		order by left_house desc");
	if ($q->rows()) {
		$output = array();
		$last_mod = 0;
		/*
		$MEMBER = new MEMBER(array('person_id'=>$id));
		$MEMBER->load_extra_info();
		$extra_info = $MEMBER->extra_info();
		if (array_key_exists('office', $extra_info)) {
			$output['offices'] = $extra_info['office'];
		}
		*/
		for ($i=0; $i<$q->rows(); $i++) {
			$out = _api_getMP_row($q->row($i));
			$output[] = $out;
			$time = strtotime($q->field($i, 'lastupdate'));
			if ($time > $last_mod)
				$last_mod = $time;
		}
		api_output($output, $last_mod);
	} else {
		api_error('Unknown person ID');
	}
}

function api_getMP_postcode($pc) {
	$pc = preg_replace('#[^a-z0-9 ]#i', '', $pc);
	if (is_postcode($pc)) {
		$electorate = postcode_to_electorate($pc);
		if ($electorate == 'CONNECTION_TIMED_OUT') {
			api_error('Connection timed out');
		} elseif ($electorate) {
			$person = _api_getMP_electorate($electorate);
			$output = $person;
			api_output($output, strtotime($output['lastupdate']));
		} else {
			api_error('Unknown postcode');
		}
	} else {
		api_error('Invalid postcode');
	}
}

function api_getMP_electorate($electorate) {
	$person = _api_getMP_electorate($electorate);
	if ($person) {
		$output = $person;
		api_output($output, strtotime($output['lastupdate']));
	} else {
		api_error('Unknown electorate, or no MP for that electorate');
	}
}

# Very similary to MEMBER's electorate_to_person_id
# Should all be abstracted properly :-/
function _api_getMP_electorate($electorate) {
	$db = new ParlDB;

	if ($electorate == '')
		return false;

	if ($electorate == 'Orkney ')
		$electorate = 'Orkney &amp; Shetland';

	$normalised = normalise_electorate_name($electorate);
	if ($normalised) $electorate = $normalised;

	$q = $db->query("SELECT * FROM member
		WHERE electorate = '" . mysql_escape_string($electorate) . "'
		AND left_reason = 'still_in_office' AND house=1");
	if ($q->rows > 0)
		return _api_getMP_row($q->row(0));

	if (get_http_var('always_return')) {
		$q = $db->query("SELECT * FROM member
			WHERE house=1 AND electorate = '".mysql_escape_string($electorate)."'
			ORDER BY left_house DESC LIMIT 1");
		if ($q->rows > 0)
			return _api_getMP_row($q->row(0));
	}
	
	return false;
}

?>
