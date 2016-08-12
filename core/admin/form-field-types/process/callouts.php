<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	// We're going to change these $bigtree entries, so save them to revert back.
	$saved = array(
		"entry" => $bigtree["entry"],
		"post_data" => $bigtree["post_data"],
		"file_data" => $bigtree["file_data"]
	);
	
	$callouts = array();
	
	if (count($this->Input)) {
		foreach ($this->Input as $number => $data) {
			// Make sure there's a callout here
			if ($data["type"]) {
				
				// Setup the new callout to emulate a normal field processing environment
				$bigtree["entry"] = array("type" => $data["type"], "display_title" => $data["display_title"]);
				$bigtree["post_data"] = $data;
				$bigtree["file_data"] = $this->FileInput[$number];
				
				$callout = new Callout($data["type"]);

				foreach ($callout->Fields as $resource) {
					$sub_field = array(
						"type" => $resource["type"],
						"title" => $resource["title"],
						"key" => $resource["id"],
						"options" => $resource["options"],
						"ignore" => false,
						"input" => $bigtree["post_data"][$resource["id"]],
						"file_input" => $bigtree["file_data"][$resource["id"]]
					);
					
					// Setup default directory
					if (empty($sub_field["options"]["directory"])) {
						$sub_field["options"]["directory"] = "files/pages/";
					}
					
					// If we JSON encoded this data and it hasn't changed we need to decode it or the parser will fail.
					if (is_string($sub_field["input"]) && is_array(json_decode($sub_field["input"], true))) {
						$sub_field["input"] = json_decode($sub_field["input"], true);
					}
					
					$sub_field = new Field($sub_field);
					$output = $sub_field->process();
					
					if (!is_null($output)) {
						$bigtree["entry"][$sub_field->Key] = $output;
					}
				}
				
				$callouts[] = $bigtree["entry"];
			}
		}
	}
	
	// Revert to saved values	
	foreach ($saved as $key => $val) {
		$bigtree[$key] = $val;
	}
	
	$this->Output = $callouts;
	