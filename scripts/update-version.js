#!/usr/bin/env node

/**
 * Update version numbers in plugin files.
 *
 * This script is called by the npm version command via package.json:
 * "version": "node ./scripts/update-version.js $npm_package_version && git add dc23-excessive-schema.php readme.txt"
 */

const fs = require('fs');
const path = require('path');

const version = process.argv[2];

if (!version) {
	console.error('Error: Version argument is required');
	process.exit(1);
}

console.log(`Updating version to ${version}...`);

// Update main plugin file
const pluginFile = path.join(__dirname, '..', 'dc23-excessive-schema.php');
let pluginContent = fs.readFileSync(pluginFile, 'utf8');
pluginContent = pluginContent.replace(
	/(\* Version:\s+)[\d.]+/,
	`$1${version}`
);
fs.writeFileSync(pluginFile, pluginContent);
console.log('✓ Updated dc23-excessive-schema.php');

// Update readme.txt if it exists
const readmeFile = path.join(__dirname, '..', 'readme.txt');
if (fs.existsSync(readmeFile)) {
	let readmeContent = fs.readFileSync(readmeFile, 'utf8');
	readmeContent = readmeContent.replace(
		/(Stable tag:\s+)[\d.]+/,
		`$1${version}`
	);
	fs.writeFileSync(readmeFile, readmeContent);
	console.log('✓ Updated readme.txt');
}

console.log('Version update complete!');
