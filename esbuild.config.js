/* eslint-disable no-console */

const fs = require('fs');
const path = require('path');

const { build } = require('esbuild');

const isProduction = process.env.NODE_ENV === 'production';

/**
 * Generate an asset manifest from esbuild's metafile.
 *
 * @param {Object} metafile - ESBuild metafile object.
 * @return {Object} Manifest mapping logical name (e.g. "jtzl-bltn-reading.js")
 *                  to the content-hashed filename.
 */
function generateManifest(metafile) {
	const manifest = {
		generated: new Date().toISOString(),
	};

	try {
		const packagePath = path.join(__dirname, 'package.json');
		if (fs.existsSync(packagePath)) {
			const packageJson = JSON.parse(
				fs.readFileSync(packagePath, 'utf8')
			);
			if (packageJson.version) {
				manifest.version = packageJson.version;
			}
		}
	} catch (error) {
		console.warn(`Could not read version: ${error.message}`);
	}

	if (metafile && metafile.outputs) {
		const hashedPattern = /^(.+?)\.[A-Z0-9]+\.([^.]+)$/;

		for (const [outputPath, outputMeta] of Object.entries(
			metafile.outputs
		)) {
			if (outputMeta.entryPoint) {
				const hashedFilename = path.basename(outputPath);
				const match = hashedFilename.match(hashedPattern);
				if (match) {
					const [, name, ext] = match;
					manifest[`${name}.${ext}`] = hashedFilename;
				}
			}
		}
	}

	return manifest;
}

/**
 * Remove stale hashed JS files no longer referenced by the manifest.
 *
 * @param {Object} manifest - Current manifest with active files.
 * @return {void}
 */
function cleanOldHashedFiles(manifest) {
	const buildDir = path.join(__dirname, 'build');

	if (!fs.existsSync(buildDir)) {
		return;
	}

	const activeFiles = new Set(Object.values(manifest).filter(Boolean));
	const hashedPattern = /^(.+?)\.[A-Za-z0-9]{8,}\.js$/;

	for (const file of fs.readdirSync(buildDir)) {
		// Sourcemaps follow the lifecycle of their hashed JS file.
		const base = file.endsWith('.map') ? file.slice(0, -4) : file;
		if (!hashedPattern.test(base)) {
			continue;
		}
		if (!activeFiles.has(base)) {
			try {
				fs.unlinkSync(path.join(buildDir, file));
				console.log(`Cleaned up old file: ${file}`);
			} catch (error) {
				console.warn(`Failed to remove ${file}: ${error.message}`);
			}
		}
	}
}

/**
 * Write the manifest to build/asset-manifest.json.
 *
 * @param {Object} manifest - Manifest object to write.
 * @return {void}
 */
function writeManifest(manifest) {
	const buildDir = path.join(__dirname, 'build');
	if (!fs.existsSync(buildDir)) {
		fs.mkdirSync(buildDir, { recursive: true });
	}

	fs.writeFileSync(
		path.join(buildDir, 'asset-manifest.json'),
		JSON.stringify(manifest, null, 2),
		'utf8'
	);

	cleanOldHashedFiles(manifest);

	console.log('Generated asset manifest');
}

/**
 * Shared IIFE configuration for the reading view's `window.BLTN` global.
 */
const baseConfig = {
	entryPoints: {
		'jtzl-bltn-reading': 'src/reading.ts',
	},
	outdir: 'build',
	bundle: true,
	platform: 'browser',
	target: 'es2019',
	format: 'iife',
	resolveExtensions: ['.ts', '.js'],
	entryNames: '[dir]/[name].[hash]',
	metafile: true,
	define: {
		'process.env.NODE_ENV': JSON.stringify(
			process.env.NODE_ENV || 'production'
		),
	},
};

const productionConfig = {
	...baseConfig,
	minify: true,
	treeShaking: true,
	drop: ['console', 'debugger'],
	legalComments: 'none',
};

const developmentConfig = {
	...baseConfig,
	sourcemap: true,
	minify: false,
};

/**
 * Build once for CLI usage.
 *
 * @return {Promise<void>}
 */
async function buildAll() {
	try {
		const config = isProduction ? productionConfig : developmentConfig;
		console.log(
			`Building Bulletin assets (${
				isProduction ? 'production' : 'development'
			})...`
		);
		const result = await build(config);
		if (result.metafile) {
			writeManifest(generateManifest(result.metafile));
		}
		console.log('Build complete.');
	} catch (error) {
		console.error('Build failed:', error);
		process.exit(1);
	}
}

module.exports = {
	baseConfig,
	productionConfig,
	developmentConfig,
};

if (require.main === module) {
	buildAll();
}
