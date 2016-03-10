<?php
	/*
		Class: BigTree\FieldType
			Provides an interface for handling BigTree field types.
	*/

	namespace BigTree;

	use BigTree;
	use BigTreeCMS;

	class FieldType extends BaseObject {

		protected $ID;

		public $Name;
		public $SelfDraw;
		public $UseCases;

		/*
			Constructor:
				Builds a FieldType object referencing an existing database entry.

			Parameters:
				field_type - Either an ID (to pull a record) or an array (to use the array as the record)
		*/

		function __construct($field_type) {
			// Passing in just an ID
			if (!is_array($field_type)) {
				$field_type = BigTreeCMS::$DB->fetch("SELECT * FROM bigtree_field_types WHERE id = ?", $field_type);
			}

			// Bad data set
			if (!is_array($field_type)) {
				trigger_error("Invalid ID or data set passed to constructor.", E_WARNING);
			} else {
				$this->ID = $field_type["id"];
				$this->Name = $field_type["name"];
				$this->SelfDraw = $field_type["self_draw"] ? true : false;
				$this->UseCases = array_filter((array) json_decode($field_type["use_cases"],true));
			}
		}

		/*
			Function: all
				Returns an array of field types.

			Parameters:
				sort - The sort directon, defaults to name ASC.
				return_arrays - Set to true to return arrays of data rather than objects.

			Returns:
				An array of entries from bigtree_field_types.
		*/

		static function all($sort = "name ASC", $return_arrays = false) {
			$field_types = BigTreeCMS::$DB->fetchAll("SELECT * FROM bigtree_field_types ORDER BY $sort");

			// Convert to FieldType objects
			if (!$return_arrays) {
				foreach ($field_types as &$type) {
					$type = new FieldType($type);
				}
			}

			return $field_types;
		}

		/*
			Function: create
				Creates a field type and its files.

			Parameters:
				id - The id of the field type.
				name - The name.
				use_cases - Associate array of sections in which the field type can be used (i.e. array("pages" => "on", "modules" => "","callouts" => "","settings" => ""))
				self_draw - Whether this field type will draw its <fieldset> and <label> ("on" or a falsey value)

			Returns:
				true if successful, false if an invalid ID was passed or the ID is already in use
		*/

		function create($id,$name,$use_cases,$self_draw) {
			// Check to see if it's a valid ID
			if (!ctype_alnum(str_replace(array("-","_"),"",$id)) || strlen($id) > 127) {
				return false;
			}

			// See if a callout ID already exists
			if (BigTreeCMS::$DB->exists("bigtree_field_types",$id)) {
				return false;
			}

			BigTreeCMS::$DB->insert("bigtree_field_types",array(
				"id" => $id,
				"name" => BigTree::safeEncode($name),
				"use_cases" => $use_cases,
				"self_draw" => ($self_draw ? "on" : null)
			));

			// Make the files for draw and process and options if they don't exist.
			$file = "$id.php";

			if (!file_exists(SERVER_ROOT."custom/admin/form-field-types/draw/$file")) {
				BigTree::putFile(SERVER_ROOT."custom/admin/form-field-types/draw/$file",'<?php
	/*
		When drawing a field type you are provided with the $field array with the following keys:
			"title" — The title given by the developer to draw as the label (drawn automatically)
			"subtitle" — The subtitle given by the developer to draw as the smaller part of the label (drawn automatically)
			"key" — The value you should use for the "name" attribute of your form field
			"value" — The existing value for this form field
			"id" — A unique ID you can assign to your form field for use in JavaScript
			"tabindex" — The current tab index you can use for the "tabindex" attribute of your form field
			"options" — An array of options provided by the developer
			"required" — A boolean value of whether this form field is required or not
	*/

	include BigTree::path("admin/form-field-types/draw/text.php");
?>');
			}

			if (!file_exists(SERVER_ROOT."custom/admin/form-field-types/process/$file")) {
				BigTree::putFile(SERVER_ROOT."custom/admin/form-field-types/process/$file",'<?php
	/*
		When processing a field type you are provided with the $field array with the following keys:
			"key" — The key of the field (this could be the database column for a module or the ID of the template or callout resource)
			"options" — An array of options provided by the developer
			"input" — The end user\'s $_POST data input for this field
			"file_input" — The end user\'s uploaded files for this field in a normalized entry from the $_FILES array in the same formatting you\'d expect from "input"

		BigTree expects you to set $field["output"] to the value you wish to store. If you want to ignore this field, set $field["ignore"] to true.
		Almost all text that is meant for drawing on the front end is expected to be run through PHP\'s htmlspecialchars function as seen in the example below.
		If you intend to allow HTML tags you will want to run htmlspecialchars in your drawing file on your value and leave it off in the process file.
	*/

	$field["output"] = htmlspecialchars($field["input"]);
?>');
			}

			if (!file_exists(SERVER_ROOT."custom/admin/ajax/developer/field-options/$file")) {
				BigTree::touchFile(SERVER_ROOT."custom/admin/ajax/developer/field-options/$file");
			}

			// Clear field type cache
			BigTree::deleteFile(SERVER_ROOT."cache/bigtree-form-field-types.json");

			// Track
			AudiTrail::track("bigtree_field_types",$id,"created");
			
			return new FieldType($id);
		}

		/*
			Function: delete
				Deletes the field type and erases its files.
		*/

		function delete() {
			$id = $this->ID;

			// Remove related files
			BigTree::deleteFile(SERVER_ROOT."custom/admin/form-field-types/draw/$id.php");
			BigTree::deleteFile(SERVER_ROOT."custom/admin/form-field-types/process/$id.php");
			BigTree::deleteFile(SERVER_ROOT."custom/admin/ajax/developer/field-options/$id.php");

			// Clear cache
			BigTree::deleteFile(SERVER_ROOT."cache/bigtree-form-field-types.json");

			// Delete and track
			BigTreeCMS::$DB->delete("bigtree_field_types",$id);
			AuditTrail::track("bigtree_field_types",$id,"deleted");
		}

		/*
			Function: reference
				Caches available field types and returns a reference array for them.

			Parameters:
				split - Whether to split the field types into separate default / custom arrays (defaults to false)
				use_case_type - A certain use case (i.e. "callouts" or "modules" -- defaults to all)

			Returns:
				Array of three arrays of field types (template, module, and callout).
		*/

		static function reference($split = false, $use_case_type = false) {
			// Used cached values if available, otherwise query the DB
			if (file_exists(SERVER_ROOT."cache/bigtree-form-field-types.json")) {
				$types = json_decode(file_get_contents(SERVER_ROOT."cache/bigtree-form-field-types.json"),true);
			} else {
				$types = array();
				$types["modules"] = $types["templates"] = $types["callouts"] = $types["settings"] = array(
					"default" => array(
						"text" => array("name" => "Text", "self_draw" => false),
						"textarea" => array("name" => "Text Area", "self_draw" => false),
						"html" => array("name" => "HTML Area", "self_draw" => false),
						"upload" => array("name" => "Upload", "self_draw" => false),
						"list" => array("name" => "List", "self_draw" => false),
						"checkbox" => array("name" => "Checkbox", "self_draw" => true),
						"date" => array("name" => "Date Picker", "self_draw" => false),
						"time" => array("name" => "Time Picker", "self_draw" => false),
						"datetime" => array("name" => "Date &amp; Time Picker", "self_draw" => false),
						"photo-gallery" => array("name" => "Photo Gallery", "self_draw" => false),
						"callouts" => array("name" => "Callouts", "self_draw" => true),
						"matrix" => array("name" => "Matrix", "self_draw" => true),
						"one-to-many" => array("name" => "One to Many", "self_draw" => false)
					),
					"custom" => array()
				);

				$types["modules"]["default"]["route"] = array("name" => "Generated Route","self_draw" => true);

				$field_types = BigTreeCMS::$DB->fetchAll("SELECT * FROM bigtree_field_types ORDER BY name");
				foreach ($field_types as $field_type) {
					$use_cases = json_decode($field_type["use_cases"],true);
					foreach ((array)$use_cases as $case => $val) {
						if ($val) {
							$types[$case]["custom"][$field_type["id"]] = array("name" => $field_type["name"],"self_draw" => $field_type["self_draw"]);
						}
					}
				}

				BigTree::putFile(SERVER_ROOT."cache/bigtree-form-field-types.json",BigTree::json($types));
			}

			// Re-merge if we don't want them split
			if (!$split) {
				foreach ($types as $use_case => $list) {
					$types[$use_case] = array_merge($list["default"],$list["custom"]);
				}
			}

			// If we only want one use case
			if ($use_case_type) {
				return $types[$use_case_type];
			}

			return $types;
		}

		/*
			Function: save
				Saves the current object properties back to the database.
		*/

		function save() {
			// Update DB
			BigTreeCMS::$DB->update("bigtree_field_types",$this->ID,array(
				"name" => BigTree::safeEncode($this->Name),
				"use_cases" => array_filter((array)$this->UseCases),
				"self_draw" => $this->SelfDraw ? "on" : ""
			));

			// Clear cache
			unlink(SERVER_ROOT."cache/bigtree-form-field-types.json");

			// Track
			AuditTrail::track("bigtree_field_types",$this->ID,"updated");
		}

		/*
			Function: update
				Updates the field type's properties and saves them back to the database.

			Parameters:
				name - The name.
				use_cases - Associate array of sections in which the field type can be used (i.e. array("pages" => "on", "modules" => "","callouts" => "","settings" => ""))
				self_draw - Whether this field type will draw its <fieldset> and <label> ("on" or a falsey value)
		*/

		function update($id,$name,$use_cases,$self_draw) {
			$this->Name = $name;
			$this->UseCases = $use_cases;
			$this->SelfDraw = $self_draw;

			$this->save();
		}

	}
