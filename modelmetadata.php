<?php

class ModelMetadata
{

	public $swNAME = 'modelmetadata';
	public $swVERSION = '0.10.0';
	public $jsonVERSION = 2;
	public $isCurrent = false;
	public $hasError = false;
	public $errorMessage = '';
	public $modelKey = '';
	public $modelName = '';
/*
	private $metaJson = array(
					"version"		=> 2,
					"legal"			=> [[
										"artist"		=> "",
										"owner"			=> "",
										"year"			=> 0,
										"license"		=> "",
										"licenseUrl"	=> "",
										"what"			=> ""
										]],
					"tags"			=> [],
					"screenshot"	=> "",
					"name"			=> "",
					"path"			=> "",
					"summary"		=> "",
					"createReadme"	=> false
				);
*/
	private $metaJson = '
{
    "version" : 2,
    "legal" : [
        {
            "artist" : "",
            "owner" : "",
            "year" : "",
            "license" : "",
            "what" : ""
        }
      ],
    "tags" : [],
    "screenshot" : "screenshot/screenshot.jpg",
    "name" : "",
    "path" : "",
    "summary" : "",
    "AutoGenerateREADME" : false,
    "createReadme" : false
}';
	private $metaPhp = 0;
	public $LICENSE = array (
			'CC0'		=> array (
							'icon'=>'https://licensebuttons.net/p/zero/1.0/88x31.png', 
							'link'=>'https://creativecommons.org/publicdomain/zero/1.0/legalcode',
							'text'=>'CC0 1.0 Universal',
							'spdx'=>'CC0',
							),
			'CC-BY'		=> array (
							'icon'=>'https://licensebuttons.net/l/by/3.0/88x31.png', 
							'link'=>'https://creativecommons.org/licenses/by-nd/4.0/legalcode',
							'text'=>'Creative Commons Attribution 4.0 International',
							'spdx'=>'CC-BY-4.0',
							),
			'CC-BY 4.0'	=> array (
							'icon'=>'https://licensebuttons.net/l/by/3.0/88x31.png', 
							'link'=>'https://creativecommons.org/licenses/by-nd/4.0/legalcode',
							'text'=>'Creative Commons Attribution 4.0 International',
							'spdx'=>'CC-BY-4.0',
							),
			'CC-BY-4.0'	=> array (
							'icon'=>'https://licensebuttons.net/l/by/3.0/88x31.png', 
							'link'=>'https://creativecommons.org/licenses/by-nd/4.0/legalcode',
							'text'=>'Creative Commons zJAttribution 4.0 International',
							'spdx'=>'CC-BY-4.0',
							),
			);

	private $metadata = array();
	
	public function __construct ($path='', $file=null) {
		$this->metaPhp = json_decode ($this->metaJson, true);
		foreach ($this->metaPhp as $key => $value) {
			$this->metadata[$key] = $value;
		}
		
		if ($path != '') {
			$this->load ($path, $file);
		}
		return $this;
	}

/*
 * Method to load a metadata JSON file
 * This method reads the specified JSON file from disk, decodes it, and stores it
 * If necessary the version is upgraded to the latest supported
 * Additional data extractions and compositions are performed for internal use
 * At the conclusion, the class object is fully ready for processing.
 * Additional data may be stored or changed with other methods
**/
	public function load ($path, $file='metadata') {
		$fullFile = $path . '/' . $file . '.json';
		if (!file_exists ($fullFile)) {
			$this->hasError = true;
			$this->errorMessage = "File not found: $fullFile";
			return $this;
		}
		
		$this->metadata = $this->_readJson ($fullFile);
		$this->_addFileInfo ($path, $file, 'json');
		//print "Checking for required update. Existing " . $this->metadata['version'] . "; target: " . $this->jsonVERSION. "\n";
		if ($this->metadata['version'] < $this->jsonVERSION) {
			$this->_updateMetadata();
			$this->isCurrent = false;
		}
		
		$this->_populateInternal ();
		$this->hasError = false;
		$this->errorMessage = "";
		return $this;
	}

/*
 * Method to overwrite JSON metadata file in the latest version
**/
	public function writeMetadata() {
		if ($this->isCurrent) {
			return $this;
		}
		$tmp = array();
		foreach ($this->metaPhp as $key => $value) {
			$tmp[$key] = $this->metadata[$key];
		}
		$tmp['version'] = $this->jsonVERSION;
		print "   Screenshot: |".$tmp['screenshot']."|\n";
		print "   Path: |".$tmp['path']."|\n";
		unset ($tmp['AutoGenerateREADME']);
		$string = json_encode($tmp, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
		
		print " .. writing METADATA to ".$this->metadata['fullPath']."\n";
		$FH = fopen ($this->metadata['fullPath'], "w");
		fwrite ($FH, $string);
		fclose ($FH);

		$this->hasError = false;
		$this->errorMessage = "";
		return $this;
	}

	public function getMetadata() {
		return $this->metadata;
	}
/*
 * Methods to output processed data
 *	README, LICENSE, etc
 *
**/
	public function writeReadme () {
		$fileReadme = $this->metadata['basePath'] . 'README.md';
		if (!$this->metadata['createReadme']) {return $this; }
		
		$screenshot = $this->metadata['screenshot'];
		$tagList = array();
		for ($ii=0; $ii<count($this->metadata['tags']); $ii++) {
			$tagList[] = sprintf ('![%s](./README-%s.md)', $this->metadata['tags'][$ii], $this->metadata['tags'][$ii]);
		}
		$tagString = join (', ', $tagList);

		$readme = array();
		$readme[] = '# ' . $this->modelName;
		$readme[] = "## Tags";
		$readme[] = $tagString;
		$readme[] = "## Summary";
		$readme[] = $this->metadata['summary'];
		$readme[] = '## Screenshot';
		$readme[] = "![screenshot](".$this->metadata['screenshot'].")";
		$readme[] = '## Legal';
		for ($ii=0; $ii<count($this->metadata['credit']); $ii++) {
			$readme[] = $this->metadata['credit'][$ii];
		}
		$readme[] = "#### Generated by " . $this->swNAME . ' ' . $this->swVERSION;
		$output = join ("\n\n", $readme);
		//print $output . "\n";

		print " .. writing README to $fileReadme\n";
		$FO = fopen ($fileReadme, 'w');
		fwrite ($FO, $output);
		fclose ($FO);

		return $this;
	}

/*
 * Create the model's LICENSE markdown file.
 * This is always created based on the information in $metadata
 * Uses data from the Summary, License, Author, Owner, Year
 */
	public function writeLicense () {

		$readme = array();
		$readme[] = '# LICENSE file for the model: ' . $this->modelName;
		$readme[] = 'All files in this directory tree are licensed as indicated below.';
		$readme[] = '* All files directly associated with the model including all text, image and binary files:';
		$readme[] = '  * [' . $this->metadata['legal'][0]['text'] . ']("' . $this->metadata['legal'][0]['licenseUrl'] . '") [SPDX license identifier: "' . $this->metadata['legal'][0]['spdx'] . '"]';
		$readme[] = '* This file and all other metadocumentation files including "metadata.json":';
		$readme[] = '  * [Creative Commons Attribtution 4.0 International]("'.$this->LICENSE['CC-BY 4.0']['link'].'") [SPDX license identifier: "CC-BY-4.0"]';
		$readme[] = 'Full license text of these licenses are available at the links above';
		$readme[] = "#### Generated by " . $this->swNAME . ' ' . $this->swVERSION;
		$output = join ("\n\n", $readme);
		//print $output . "\n";

		print " .. writing LICENSE to ".$this->metadata['baseLicensePath']."\n";
		$FO = fopen ($this->metadata['baseLicensePath'], 'w');
		fwrite ($FO, $output);
		fclose ($FO);

		return $this;
	}

/*
 * Methods to deal with tags & license(s)
 * set* sets the entire structure for '*'
 * add* adds to the existing structure
 * get* returns the structure for '*'
 *
 * Licenses must contain at least a name, if the name is standard (see LICENSES)
 *	if the name is not standard, then it needs to also include the URL of the license text
 *	After the new license is in place, the system will do a cleanup, then regenerate the credits
**/
	public function setWriteReadme ($write=false) {
		$this->metadata['createReadme'] = $write;
		$this->metadata['AutoGenerateREADME'] = $this->metadata['createReadme'];
		$this->hasError = false;
		$this->errorMessage = "";
		return $this;
	}
	public function setTags ($tags=null) {
		$this->metadata['tags'] = $tags;
		$this->isCurrent = false;
		$this->hasError = false;
		$this->errorMessage = "";
		return $this;
	}
	public function setSummary ($newSummary='') {
		$this->metadata['summary'] = $newSummary;
		$this->isCurrent = false;
		$this->hasError = false;
		$this->errorMessage = "";
		return $this;
	}
	public function addTags ($tags=null) {
		if (!isset($this->metadata['tags'])) {
			$this->metadata['tags'] = $tags;
		} else {
			for ($ii=0; $ii<count($tags); $ii++) {
				if (!preg_grep("/$tags[$ii]/i",$this->metadata['tags'])) {
					$this->metadata['tags'][] = strtolower ($tags[$ii]);
				}
			}
		}
		$this->isCurrent = false;
		$this->hasError = false;
		$this->errorMessage = "";
		return $this;
	}
	public function addLicense ($license, $removeAll=false) {
		if (!isset($license)) {
			$this->hasError = false;
			$this->errorMessage = "";
			return $this;
		}
		if ($removeAll) {$this->metadata['legal'] = null;}
		$this->_addLicense ($license);

		// Generate link to license if standard license and link not provided
		$this->_cleanupLicense (false);
		$this->metadata['credit'] = $this->_generateCredits();

		//print "Before return\n";
		//print_r($this->metadata['credit']);
		//print "==================================\n\n";
		$this->isCurrent = false;
		$this->hasError = false;
		$this->errorMessage = "";
		return $this;
	}
	private function _addLicense ($license) {
		$ndx = (isset($this->metadata['legal'][0])) ? count($this->metadata['legal']) : 0;
		//print "Adding |$ndx| license\n";
		$this->metadata['legal'][$ndx]['license']		= (isset($license['license'])) ? $license['license'] : '';
		$this->metadata['legal'][$ndx]['licenseUrl']	= (isset($license['licenseUrl'])) ? $license['licenseUrl'] : '';
		$this->metadata['legal'][$ndx]['artist']		= (isset($license['artist'])) ? $license['artist'] : '';
		$this->metadata['legal'][$ndx]['year']			= (isset($license['year'])) ? $license['year'] : '';
		$this->metadata['legal'][$ndx]['owner']			= (isset($license['owner'])) ? $license['owner'] : '';
		$this->metadata['legal'][$ndx]['what']			= (isset($license['what'])) ? $license['what'] : '';
		$this->metadata['legal'][$ndx]['text']			= (isset($license['text'])) ? $license['text'] : $this->metadata['legal'][$ndx]['license'];
	}

	
// Populates internal values from the read in ones
	private function _populateInternal () {
		//$this->metadata->foo = f ($this->metadata->bar);
		if (isset($this->metadata['name'])) {
			$this->modelKey  = $this->metadata['name'];
			$this->modelName = $this->metadata['name'];
		}

		// Minor change that does not warrant a version # upgraded
		if (!isset($this->metadata['legal'])) {
			$this->isCurrent = false;
			$this->metadata['legal'] = $this->metadata['Legal'];
		}

		// Generate link to license if standard license and link not provided
		$this->_cleanupLicense ();
		
		$this->metadata['legalGood'] = ($this->metadata['legal'][0]['owner'] == '_No Owner_' || $this->metadata['legal'][0]['year'] == 0) ? false : true;

		$this->metadata['createReadme']	= (isset($this->metadata['AutoGenerateREADME'])) ? $this->metadata['AutoGenerateREADME'] && $this->metadata['createReadme'] : $this->metadata['createReadme'];
		$this->metadata['AutoGenerateREADME']	= $this->metadata['createReadme'];
		$this->metadata['credit']		= $this->_generateCredits();
		$this->metadata['summary'] = ($this->metadata['summary'] == '') ? '_No Summary_' : $this->metadata['summary'];
		$this->_handleScreenshot();

		// print "Update/create any other fields that are needed\n";
		return;
	}

/*
 * Handles the screenshot for this model
**/	
	private function _handleScreenshot () {
		$path = $this->metadata['path'];
		$shotHeight = 150;
		$screenshot = $this->metadata['screenshot'];
		$tmp = explode ('.', $screenshot);
		$shotPathName = $tmp[0];
		$shotExtension = $tmp[1];

// basePath* is from the repo root directory
// path* is from the model directory
		$this->metadata['screenshotType'] = $shotExtension;
		$this->metadata['basePath'] = $path . '/';
		$this->metadata['basePathModel'] = 'path-to-model';
		$this->metadata['basePathShot'] = $path . '/' . $this->metadata['screenshot'];
		$this->metadata['UriShot'] = $this->metadata['basePathShot'];
		$this->metadata['shotHeight'] = sprintf ('%s-x%d.%s', $shotPathName, $shotHeight, $shotExtension);
		$this->metadata['basePathHeight'] = $path . '/' . $this->metadata['shotHeight'];

		return;
	}

/*
 * Cleans up license information
**/	
	private function _cleanupLicense ($terminate=false) {
		//print "In _cleanupLicense\n";
		//print_r($this->metadata);
		//print_r($this->metadata['legal'][0]);

		if ($terminate) {exit;}
		for ($ii=0; $ii<count($this->metadata['legal']); $ii++) {
			$license = $this->metadata['legal'][$ii]['license'];
			$link = (isset($this->metadata['legal'][$ii]['licenseUrl'])) ? $this->metadata['legal'][$ii]['licenseUrl'] : '';
			if ($link == '') {
				if (isset($this->LICENSE[$license])) {
					$link = $this->LICENSE[$license]['link'];
					$text = $this->LICENSE[$license]['text'];
					$spdx = $this->LICENSE[$license]['spdx'];
					$icon = $this->LICENSE[$license]['icon'];

				} else {			// Non-standard license
					$link = '';
					$text = $license;
					$spdx = '';
					$icon = '';
				}
				$this->metadata['legal'][$ii]['licenseUrl'] = $link;
				$this->metadata['legal'][$ii]['text'] = $text;
				$this->metadata['legal'][$ii]['spdx'] = $spdx;
				$this->metadata['legal'][$ii]['icon'] = $icon;
			}
		}
		//print "At completion\n";
		//print_r($this->metadata['legal'][0]);
		//print "==================================\n\n";
		return;
	}

/*
 * Generates the credit entry for this model
**/	
	private function _generateCredits () {
		$credit = array();
		for ($ii=0; $ii<count($this->metadata['legal']); $ii++) {
			//print "Creating credit lines for license #$ii\n";
			$credit[] = sprintf ("&copy; %04d, %s. [%s](%s)", $this->metadata['legal'][$ii]['year'], $this->metadata['legal'][$ii]['owner'], $this->metadata['legal'][$ii]['license'], $this->metadata['legal'][$ii]['licenseUrl']);
			$credit[] = sprintf (" - %s for %s", $this->metadata['legal'][$ii]['artist'], $this->metadata['legal'][$ii]['what']);
		}
		//print "Generating credits\n";
		//print_r($credit);
		
		return $credit;
	}

/*
 * Updates the low-version JSON structure to match the current version
 * Information updated:
 *	Legal & Credits
**/	
	private function _updateMetadata () {
		$artist  = (isset($this->metadata->author) && $this->metadata->author != '') ? $this->metadata->author : '';
		$artist  = (isset($this->metadata->artist)) ? $this->metadata->artist : $artist;
		$artist  = ($artist == '') ? '_No Artist_' : $artist;
		$owner  = (isset($this->metadata->owner) && $this->metadata->owner != '') ? $this->metadata->owner : '_No Owner_';
		$license = (isset($this->metadata->license) && $this->metadata->license != '') ? $this->metadata->licenseText : '_No License_';
		$year = (isset($this->metadata->year) && $this->metadata->year > 1900) ? $this->metadata->year : 0;
		$legal = array ();
		$legal[] = array(
						'year'			=> $year,
						'owner'			=> $owner,
						'license'		=> $license,
						'licenseUrl'	=> '',
						'artist'		=> $artist,
						'what'			=> ''
					);
		$this->metadata['legal'] = $legal;
	}
	
// Reads the JSON model metadata file and returns the data structure
	private function _readJson ($fullFile) {
		$jsonString = file_get_contents ($fullFile);
		return json_decode ($jsonString, true);
	}

// Adds file info to internal data structure
// This is necessary so the file can be overwritten later on
	private function _addFileInfo ($path, $file, $extension) {
		$unixPath = str_replace ('\\', '/', $path);
		$unixFull = sprintf ("%s/%s.%s", $unixPath, $file, $extension);
		$this->metadata['path'] = $unixPath;
		$this->metadata['filename'] = $file;
		$this->metadata['fullPath'] = $unixFull;
		$this->metadata['baseReadmePath'] = $unixFull;
		$this->metadata['baseLicensePath'] = sprintf ("%s/LICENSE.md", $unixPath);
		$this->modelKey = $file;
		$this->modelName = $file;
		$this->isCurrent = true;
	}

}

// Load the user-input data for each model. This is used to modify the model
// metadata after the JSON is loaded
$modelMetadata = getModelData();

// Simple tests

/*
 * TODOs
 *	Update write README capabilities
 *	Add write JSON capabilities
 *	Improve handling of no license & no author
 *	Verify writeLicense
 *
 *	Add processing of entire directory
 *	Improve processing of TAGS
 *	Generate repo READMEs
 *	Generate repo LICENSE / SPDX stuff
 *
**/

$mm = new ModelMetadata('2.0\2CylinderEngine', 'metadata');
//print_r ($modelMetadata[$mm->modelKey]);
//print_r ($mm->getMetadata());
print_r ($modelMetadata[$mm->modelName]);
/*
$mm = $mm
		->addTags(['no-license','cad', 'no-author'])
		->addLicense ( array(
						'license'=>'Khronos-Archive', 
						'licenseUrl'=>'', 
						'artist'=>'Khronos', 
						'owner'=>'Khronos', 
						'year'=>'2017', 
						'what'=>'Everything'),
					true)
		->setSummary ($modelMetadata[$mm->modelName]['Summary'])
		->setWriteReadme (true);
*/
$mm = $mm->writeMetadata()->writeReadme()->writeLicense();
print_r ($mm->getMetadata());
//print_r($mm);
exit;

/*
 * TODO:
 *	- Verify that UpdateLicense column is correct for all models
 *	- Verify that AutoGenerateREADME column is correct for all models
 *	- Verify that the output is correct for METADATA for all models
 *	- Run with real METADATA output
 *	- Run with real LICENSE and README output
 *	- Create site-wide license infoHi Eoin
**/
$modelFolder= './2.0';
$folder = dir ($modelFolder);
$folderDotDirs = array ($modelFolder.'/.', $modelFolder.'/..');
while (false !== ($model = $folder->read())) {
	$modelDir = $folder->path . '/' . $model;
	if (is_dir($modelDir) && !($model == '.' || $model == '..')) {
		print "\nProcessing $modelDir\n";
		$mm = new ModelMetadata($modelDir, 'metadata');
		if ($modelMetadata[$mm->modelName]['UpdateLicense'] != 'FALSE') {
			$mm = $mm
					->addLicense ( array(
									'license'=>$modelMetadata[$mm->modelName]['License'],
									'licenseUrl'=>'', 
									'artist'=>$modelMetadata[$mm->modelName]['Author'],
									'owner'=>$modelMetadata[$mm->modelName]['Owner'],
									'year'=>$modelMetadata[$mm->modelName]['Year'],
									'what'=>'Everything'),
								true)
					->setWriteReadme ($modelMetadata[$mm->modelName]['AutoGenerateREADME']);
		}
		$mm = $mm
				->setSummary ($modelMetadata[$mm->modelName]['Summary'])
				->writeMetadata()
				->writeReadme()
				->writeLicense();
	}
}
$folder->close();


exit;

/*
 * Returns a hash of a hash of the CSV containing the updated model data
 *	Primary Key is model name. Secondary keys are the column name that corresponds to the JSON field
 *	This data generally replaces the license from the JSON metadata
**/
function getModelData() {
	$dataFile = 'ModelRepoData.csv';
	$FH = fopen ($dataFile, "r");
	$ModelData = array();
	$keys = fgetcsv($FH, 5000);
	while (($row = fgetcsv($FH, 5000)) !== false) { 
		$new = array();
		for ($ii=0; $ii<count($row); $ii++) {
			$new[$keys[$ii]] = $row[$ii];
		}
		$ModelData[$new['Key']] = $new;
	}
	fclose ($FH);
	return $ModelData;
}

?>
