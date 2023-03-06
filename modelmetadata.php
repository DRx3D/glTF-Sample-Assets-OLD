<?php

/*
 * PHP script for automatically processing metadata files in the glTF Sample Repo
 * And creating the necessary support files including README and LICENSE
 *
 * Author: Leonard Daly, Daly Realism
 * Significant contributions from
 *	Marco Hutter (JSON design, tag structure, and overall design)
 *	Ed Mackey (license resolution for models in the Repo)
 *
 *	Copyright 2023, The Khronos Group.
 *	Licensed under the Apache License, Version 2.0 (the "License");
 *	you may not use this file except in compliance with the License.
 *	You may obtain a copy of the License at
 *	
 *	    http://www.apache.org/licenses/LICENSE-2.0
 *	
 *	Unless required by applicable law or agreed to in writing, software
 *	distributed under the License is distributed on an "AS IS" BASIS,
 *	WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *	See the License for the specific language governing permissions and
 *	limitations under the License.
**/

// Define a class to handle a single model
class ModelMetadata
{
	
// Public constants 
	public $swNAME = 'modelmetadata';
	public $swVERSION = '0.15.6';
	public $jsonVERSION = 2;
	
// Public variables for internal states
	public $isCurrent = false;
	public $hasError = false;
	public $errorMessage = '';
	public $modelKey = '';
	public $modelName = '';

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

// Placeholder for later use
	private $metaPhp = 0;
	
// Array to convert from a variety of user values to PHP's true or false
	public $TF = array (
						'FALSE'	=> false,
						'0'		=> false,
						'F'		=> false,
						'false'	=> false,
						'no'	=> false,
						'NO'	=> false,
						false	=> false,
						'TRUE'	=> true,
						'1'		=> true,
						'T'		=> true,
						'true'	=> true,
						'yes'	=> true,
						'YES'	=> true,
						true	=> true
						);

// Array of standard model licenses
	public $LICENSE = array (
			'CC0'		=> array (
							'icon'=>'https://licensebuttons.net/p/zero/1.0/88x31.png', 
							'link'=>'https://creativecommons.org/publicdomain/zero/1.0/legalcode',
							'text'=>'CC0 1.0 Universal',
							'spdx'=>'CC0',
							),
			'CC-BY'		=> array (
							'icon'=>'https://licensebuttons.net/l/by/3.0/88x31.png', 
							'link'=>'https://creativecommons.org/licenses/by/4.0/legalcode',
							'text'=>'Creative Commons Attribution 4.0 International',
							'spdx'=>'CC-BY-4.0',
							),
			'CC-BY 4.0'	=> array (
							'icon'=>'https://licensebuttons.net/l/by/3.0/88x31.png', 
							'link'=>'https://creativecommons.org/licenses/by/4.0/legalcode',
							'text'=>'Creative Commons Attribution 4.0 International',
							'spdx'=>'CC-BY-4.0',
							),
			'CC-BY-4.0'	=> array (
							'icon'=>'https://licensebuttons.net/l/by/3.0/88x31.png', 
							'link'=>'https://creativecommons.org/licenses/by/4.0/legalcode',
							'text'=>'Creative Commons zJAttribution 4.0 International',
							'spdx'=>'CC-BY-4.0',
							),
			);

// Model's metadata, either stored in the Repo or derrived from it
	private $metadata = array();
	
// Method construct the object
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
	public function writeReadme ($tagListings=null) {
		$fileReadme = $this->metadata['basePath'] . 'README.md';
		if (!$this->metadata['createReadme']) {return $this; }
		//print " .. Updating README\n";
		
		$screenshot = $this->metadata['screenshot'];
		$tagList = array();
		for ($ii=0; $ii<count($this->metadata['tags']); $ii++) {
			$path = $this->_getTagListingPath ($this->metadata['tags'][$ii], $tagListings);
			if ($path == '') {
				$tagList[] = sprintf ('%s', $this->metadata['tags'][$ii], $this->metadata['tags'][$ii]);
			} else {
				$tagList[] = sprintf ('![%s](../../%s)', $this->metadata['tags'][$ii], $path);
			}
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

		print " .. writing README to $fileReadme\n";
		$FO = fopen ($fileReadme, 'w');
		fwrite ($FO, $output);
		fclose ($FO);

		return $this;
	}
	private function _getTagListingPath ($tag, $tagListings) {
		if (count($tagListings) < 1) {return ''; }
		for ($ii=0; $ii<count($tagListings); $ii++) {
			if (isset($tagListings[$ii]['tags'][0]) && $tag == $tagListings[$ii]['tags'][0]) {
				return $tagListings[$ii]['file'];
			}
		}
		return '';
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
		$this->metadata['createReadme'] = (isset($this->TF[$write])) ? $this->TF[$write] : false;
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
		$this->metadata['createReadme'] = $this->TF[$this->metadata['createReadme']];
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
		$path = str_replace (' ', '%20', $path);
		$this->metadata['path'] = $path;

		$tmp = explode ('/', $path);				// Get the model directory. It is 
		$modelDirectory = $tmp[count($tmp)-1];		// the last item in $path
		
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
		
		$this->metadata['modelPath'] = sprintf ('%s/glTF/%s.gltf', $path, $modelDirectory);


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

/*
 * Processing control flags.
 * These are only used when doing a mass update or conversion
 *	$useUserModelTags - reads the model tag update file (ModelRepoTagData.csv). See getModelTagData
 ^	$useUserModelData - reads the model metadata update file (). See getModelData
**/
$useUserModelTags = false;		// Update model tags
$useUserModelData = false;		// Update model metadata 

/*
 * Define internal arrays. 
 *	$listings is a structure for managing supported tags. All supported tags & tag combinations
 *		need to be included here
**/
$listings = array (
					array('type'=>'List', 'file'=>'Models.md', 'tags'=>array(), 'summary'=>'All models listed alphabetically/'),
					array('type'=>'List', 'file'=>'Models-core.md', 'tags'=>array('core'), 'summary'=>'Models that only use the core glTF V2.0 features and capabilities.'),
					array('type'=>'List', 'file'=>'Models-issues.md', 'tags'=>array('issues'), 'summary'=>'Models with one or more issues with respect to ownership or license.'),
					array('type'=>'List', 'file'=>'Models-showcase.md', 'tags'=>array('showcase'), 'summary'=>'Models that are featured in some glTF/Khronos publicity.'),
					array('type'=>'List', 'file'=>'Models-testing.md', 'tags'=>array('testing'), 'summary'=>'Models that are used for testing various features or capabilities of importers, viewers, or converters.'),
					array('type'=>'List', 'file'=>'Models-video.md', 'tags'=>array('video'), 'summary'=>'Models used in any glTF video tutorial.'),
					array('type'=>'List', 'file'=>'Models-written.md', 'tags'=>array('written'), 'summary'=>'Models used in any written glTF tutorial or guide.')
					);
					

/*
 * TODOs
 *	Verify writeLicense
 *
 *	Generate repo LICENSE / SPDX stuff
 *
**/

// Load all model objects
	$allModels = getAllModels ($listings, './2.0');

// If requested load the user input metadata for each model. 
if ($useUserModelData) {
	$modelMetadata = getModelData();
	$allModels = updateModelsMetadata ($allModels, $modelMetadata, $tagListings);
}
// If requested load the user tag settings for each model. 
if ($useUserModelTags) {
	$modelTagData = getModelTagData();
	$allModels = updateModelsTags ($allModels, $modelTagData, $tagListings);
}

print "===============================\n";

// Now create various Repo files
for ($ii=0; $ii<count($listings); $ii++) {
	createReadme ($listings[$ii], $allModels, $listings, $listings[$ii]['tags']);
}

//createTagCsv ('model-metadata.csv', $allModels);


exit;



// Function for creating READMEs
function createReadme ($tagStrcture, $metaAll, $listings, $tags=array('')) {
	$urlSampleViewer = 'https://github.khronos.org/glTF-Sample-Viewer-Release/';
	$urlModelRepoRaw = 'https://raw.GithubUserContent.com/KhronosGroup/glTF-Sample-Models/master';
	
	$F = fopen ($tagStrcture['file'], 'w');
	$section = 'Tagged...';
	if (count($tags) == 0 || $tags[0] == '') {
		$section = 'All models';
		$singleTag = '';
	} else {
		$section = 'Models tagged with **' . join(', ', $tags) . '**';
		$singleTag = $tags[0];
	}
	$type = $tagStrcture['type'];
	print "Generating $type for $section\n";
	
	fwrite ($F, "# glTF 2.0 Sample Models\n\n");
	fwrite ($F, "## $section\n\n");
	fwrite ($F, $tagStrcture['summary']."\n\n");
	
	for ($ii=0; $ii<count($listings); $ii++) {
		if (count($listings[$ii]['tags']) > 0) {
			$tagItem = '#' . join(', #', $listings[$ii]['tags']);
		} else {
			$tagItem = '#all';
		}
		$otherTags[] = sprintf ("[%s](%s)", $tagItem, $listings[$ii]['file']);
	}
	fwrite ($F, "## Other Tagged Listings\n\n");
	fwrite ($F, "* " . join("\n* ", $otherTags) . "\n\n");

	if ($type == 'Image') {
		$fmtString = "[![%s](%s)](%s)\n";
		for ($ii=0; $ii<count($metaAll); $ii++) {
			fwrite ($F, sprintf ($fmtString, 
						$metaAll[$ii]->{'name'}, 
						$metaAll[$ii]->{'UriHeight'},
						$metaAll[$ii]->{'UriReadme'}
						));
		}

	} else if ($type == 'Detailed') {
		fwrite ($F, "| Model   | Legal | Description |\n");
		fwrite ($F, "|---------|-------|-------------|\n");
		$fmtString = "| [%s](%s) <br> ![](%s) | %s | %s |\n";
		//$modelMeta = $metaAll[0]->getMetadata();
		//print_r ($modelMeta);

		for ($ii=0; $ii<count($metaAll); $ii++) {
			$modelMeta = $metaAll[$ii]->getMetadata();
			$summary = ($modelMeta['summary'] == '') ? '**NO DESCRIPTION**' : $modelMeta['summary'];

			fwrite ($F, sprintf ($fmtString, 
						$modelMeta['name'], 
						$modelMeta['path'].'/README.md',
						$modelMeta['basePathShot'],
						join("<br>", $modelMeta['credit']),
						$summary,
						));
		}
	} else if ($type == 'List') {
		fwrite ($F, "| Model   | Description |\n");
		fwrite ($F, "|---------|-------------|\n");
		$fmtString = "| [%s](%s)<br>[![%s](%s)](%s)<br>[Show in Sample Viewer](%s?model=%s/%s) | %s<br>Credit:<br>%s |\n";

		for ($ii=0; $ii<count($metaAll); $ii++) {
			$modelMeta = $metaAll[$ii]->getMetadata();
			if ($singleTag == '' || (is_array($modelMeta['tags']) && in_array($singleTag, $modelMeta['tags']))) {
				$summary = ($modelMeta['summary'] == '') ? '**NO DESCRIPTION**' : $modelMeta['summary'];

				fwrite ($F, sprintf ($fmtString, 
							$modelMeta['name'], 
							$modelMeta['path'].'/README.md',
							$modelMeta['name'], 
							$modelMeta['basePathShot'],
							$modelMeta['path'].'/README.md',
							$urlSampleViewer, $urlModelRepoRaw, $modelMeta['modelPath'],
							$summary,
							join("<br>", $modelMeta['credit']),
							));
			}
		}
	}
	fwrite ($F, "---\n");
	fwrite ($F, sprintf ("\n### Copyright\n\n&copy; %d, The Khronos Group.\n\n**License:** [Creative Commons Attribtution 4.0 International](%s)\n", 2023, $metaAll[0]->LICENSE['CC-BY 4.0']['link']));
	fwrite ($F, sprintf ("\n#### Generated by %s v%s\n", $metaAll[0]->swNAME, $metaAll[0]->swVERSION));

	fclose ($F);
	return;
}

// Function for creating a list of tgags per model
function createTagCsv ($fname, $metaAll) {
	$F = fopen ($fname, 'w');
	fwrite ($F, "\"Model Name\",Tags\n");
	for ($ii=0; $ii<count($metaAll); $ii++) {
		$modelMeta = $metaAll[$ii]->getMetadata();
		if (is_array($modelMeta['tags'])) {
			fwrite ($F, sprintf("\"%s\",\"%s\"\n", $modelMeta['name'], join('","', $modelMeta['tags'])));
		} else {
			fwrite ($F, sprintf("\"%s\"\n", $modelMeta['name']));
		}
	}
	fclose ($F);
	return;
}
	



/*
 * Update tags of all models.
 *	Metadata of all models is reflect in the new tag set.
 *	These are replacement tags (existing tags are removed)
 *	Readme file may be updated
 *
 *	Arguments
 *		$allModels - array of model objects (see getAllModels)
 *		$modelsTags	 - hash of model tags. All models need to have an entry in $modelsTags referred by modelName.
 *		$tagListings - Data structure of supported tags. 
 *
 */

function updateModelsMetadata ($allModels, $modelUpdateMetadata, $tagListings) {

	for ($ii=0; $ii<count($allModels); $ii++) {
		$modelName = $allModels[$ii]->modelName;
		print "\nMetadata processing $modelName\n";
		if ($modelUpData[$modelName]['UpdateLegal'] != 'FALSE') {
			$allModels[$ii] = $allModels[$ii]
								->addLicense ( array(
										'license'=>$modelUpData[$modelName]['License'],
										'licenseUrl'=>'', 
										'artist'=>$modelUpData[$modelName]['Author'],
										'owner'=>$modelUpData[$modelName]['Owner'],
										'year'=>$modelUpData[$modelName]['Year'],
										'what'=>'Everything'),
									true)
								->setWriteReadme ($modelUpData[$modelName]['AutoGenerateREADME']);
		}
		$allModels[$ii] = $allModels[$ii]
								->setSummary ($modelUpData[$modelName]['Summary'])
								->writeMetadata()
								->writeReadme($tagListings)
								->writeLicense();
	}
	return $allModels;
}
/*
 * Update tags of all models.
 *	Metadata of all models is reflect in the new tag set.
 *	These are replacement tags (existing tags are removed)
 *	Readme file may be updated
 *
 *	Arguments
 *		$allModels - array of model objects (see getAllModels)
 *		$modelsTags	 - hash of model tags. All models need to have an entry in $modelsTags referred by modelName.
 *		$tagListings - Data structure of supported tags. 
 *
 */
function updateModelsTags ($allModels, $modelsTags, $tagListings) {

	for ($ii=0; $ii<count($allModels); $ii++) {
		$modelName = $allModels[$ii]->modelName;
		print "\nTag processing $modelName\n";
		$allModels[$ii] = $allModels[$ii]
										->setTags ($modelsTags[$modelName])
										->writeMetadata()
										->writeReadme($tagListings);
	}
	return $allModels;
}

/*
 * Get all models into a single data structure (array of hashes of ...)
 *	This routine processes each model and performs internal updates
 *	License, Metadata, and Readme files may (or will) be updated
 *
 *	Model data array (of model objects) is returned
**/
function getAllModels ($tagListings, $modelFolder='') {
	if ($modelFolder == '') {return null;}

	$folder = dir ($modelFolder);
	$folderDotDirs = array ($modelFolder.'/.', $modelFolder.'/..');
	while (false !== ($model = $folder->read())) {
		$modelDir = $folder->path . '/' . $model;
		if (is_dir($modelDir) && !($model == '.' || $model == '..')) {
			print "\nProcessing $modelDir\n";
			$mm = new ModelMetadata($modelDir, 'metadata');
			$mm = $mm
					->writeMetadata()
					->writeReadme($tagListings)
					->writeLicense();
			$allModels[] = $mm;
		}
	}
	$folder->close();
	return $allModels;
}

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

/*
 * Returns a hash of a hash of the CSV containing the updated model tag data
 *	Primary Key is model name. Each primary key contains an array of tags for that model
**/
function getModelTagData() {
	$dataFile = 'ModelRepoTagData.csv';
	$FH = fopen ($dataFile, "r");
	$ModelData = array();
	$keys = fgetcsv($FH, 5000);
	while (($row = fgetcsv($FH, 5000)) !== false) { 
		$new = array();
		for ($ii=1; $ii<count($row); $ii++) {
			if ($row[$ii] == 'TRUE') {
				$new[] = $keys[$ii];
			}
		}
		$ModelData[$row[0]] = $new;
	}
	fclose ($FH);
	return $ModelData;
}
?>
