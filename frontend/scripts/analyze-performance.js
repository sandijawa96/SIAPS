const lighthouse = require('lighthouse');
const chromeLauncher = require('chrome-launcher');
const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const webpack = require('webpack');
const BundleAnalyzerPlugin = require('webpack-bundle-analyzer').BundleAnalyzerPlugin;

// Fungsi untuk menjalankan Lighthouse audit
async function runLighthouseAudit(url) {
  const chrome = await chromeLauncher.launch({
    chromeFlags: ['--headless', '--disable-gpu', '--no-sandbox']
  });

  const options = {
    logLevel: 'info',
    output: 'html',
    onlyCategories: ['performance', 'accessibility', 'best-practices', 'seo'],
    port: chrome.port
  };

  const runnerResult = await lighthouse(url, options);
  const reportHtml = runnerResult.report;
  fs.writeFileSync('performance-report.html', reportHtml);

  await chrome.kill();
  return runnerResult.lhr;
}

// Fungsi untuk menganalisis bundle size
function analyzeBundleSize() {
  const config = require('../webpack.config.js');
  config.plugins.push(new BundleAnalyzerPlugin({
    analyzerMode: 'static',
    reportFilename: 'bundle-analysis.html',
    openAnalyzer: false
  }));

  webpack(config, (err, stats) => {
    if (err || stats.hasErrors()) {
      console.error('Bundle analysis failed:', err || stats.toString());
      return;
    }
    console.log('Bundle analysis completed. See bundle-analysis.html');
  });
}

// Fungsi untuk mengecek unused dependencies
function checkUnusedDependencies() {
  try {
    execSync('npx depcheck', { stdio: 'inherit' });
  } catch (error) {
    console.error('Error checking unused dependencies:', error);
  }
}

// Fungsi untuk menganalisis duplicate dependencies
function analyzeDuplicateDependencies() {
  try {
    execSync('npm dedupe', { stdio: 'inherit' });
    execSync('npm ls', { stdio: 'inherit' });
  } catch (error) {
    console.error('Error analyzing duplicate dependencies:', error);
  }
}

// Fungsi untuk mengecek performance metrics dari source code
function analyzeSourceCode() {
  const metrics = {
    totalComponents: 0,
    memoizedComponents: 0,
    lazyLoadedComponents: 0,
    largeComponents: 0 // Components dengan lebih dari 200 baris
  };

  function scanDirectory(dir) {
    const files = fs.readdirSync(dir);
    
    files.forEach(file => {
      const fullPath = path.join(dir, file);
      const stat = fs.statSync(fullPath);
      
      if (stat.isDirectory()) {
        scanDirectory(fullPath);
      } else if (file.match(/\.(jsx?|tsx?)$/)) {
        const content = fs.readFileSync(fullPath, 'utf8');
        
        // Count components
        metrics.totalComponents += (content.match(/export default/g) || []).length;
        
        // Count memoized components
        metrics.memoizedComponents += (content.match(/React\.memo|memo\(/g) || []).length;
        
        // Count lazy loaded components
        metrics.lazyLoadedComponents += (content.match(/React\.lazy|lazy\(/g) || []).length;
        
        // Count large components
        if (content.split('\n').length > 200) {
          metrics.largeComponents++;
        }
      }
    });
  }

  scanDirectory(path.resolve(__dirname, '../src'));
  return metrics;
}

// Fungsi untuk menghasilkan laporan performa
async function generatePerformanceReport() {
  console.log('🔍 Analyzing application performance...\n');

  // 1. Lighthouse Audit
  console.log('Running Lighthouse audit...');
  const lighthouseResults = await runLighthouseAudit('http://localhost:3000');
  console.log('\nLighthouse Scores:');
  console.log('Performance:', lighthouseResults.categories.performance.score * 100);
  console.log('Accessibility:', lighthouseResults.categories.accessibility.score * 100);
  console.log('Best Practices:', lighthouseResults.categories['best-practices'].score * 100);
  console.log('SEO:', lighthouseResults.categories.seo.score * 100);

  // 2. Bundle Analysis
  console.log('\nAnalyzing bundle size...');
  analyzeBundleSize();

  // 3. Dependencies Check
  console.log('\nChecking dependencies...');
  checkUnusedDependencies();
  analyzeDuplicateDependencies();

  // 4. Source Code Analysis
  console.log('\nAnalyzing source code...');
  const sourceMetrics = analyzeSourceCode();
  console.log('\nSource Code Metrics:');
  console.log('Total Components:', sourceMetrics.totalComponents);
  console.log('Memoized Components:', sourceMetrics.memoizedComponents);
  console.log('Lazy Loaded Components:', sourceMetrics.lazyLoadedComponents);
  console.log('Large Components:', sourceMetrics.largeComponents);

  // Generate final report
  const report = {
    timestamp: new Date().toISOString(),
    lighthouse: lighthouseResults,
    sourceMetrics,
    recommendations: []
  };

  // Add recommendations based on analysis
  if (sourceMetrics.memoizedComponents / sourceMetrics.totalComponents < 0.3) {
    report.recommendations.push('Consider memoizing more components to prevent unnecessary re-renders');
  }

  if (sourceMetrics.lazyLoadedComponents < 5) {
    report.recommendations.push('Implement more lazy loading for better initial load performance');
  }

  if (sourceMetrics.largeComponents > 0) {
    report.recommendations.push('Consider breaking down large components into smaller, reusable pieces');
  }

  // Save report
  fs.writeFileSync(
    'performance-analysis-report.json',
    JSON.stringify(report, null, 2)
  );

  console.log('\n✅ Performance analysis complete!');
  console.log('Check performance-report.html and bundle-analysis.html for detailed reports');
}

// Run performance analysis
generatePerformanceReport().catch(console.error);

// Export functions for use in other scripts
module.exports = {
  runLighthouseAudit,
  analyzeBundleSize,
  checkUnusedDependencies,
  analyzeDuplicateDependencies,
  analyzeSourceCode,
  generatePerformanceReport
};
