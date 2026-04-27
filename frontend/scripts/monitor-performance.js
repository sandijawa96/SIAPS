const { performance, PerformanceObserver } = require('perf_hooks');
const fs = require('fs');
const path = require('path');
const os = require('os');

class PerformanceMonitor {
  constructor() {
    this.metrics = {
      memory: [],
      cpu: [],
      renderTimes: [],
      networkRequests: [],
      errors: [],
      fps: []
    };

    this.logFile = path.join(__dirname, '../logs/performance.log');
    this.ensureLogDirectory();

    // Initialize performance observers
    this.setupObservers();
  }

  ensureLogDirectory() {
    const logDir = path.dirname(this.logFile);
    if (!fs.existsSync(logDir)) {
      fs.mkdirSync(logDir, { recursive: true });
    }
  }

  setupObservers() {
    // Monitor render performance
    const renderObserver = new PerformanceObserver((list) => {
      const entries = list.getEntries();
      entries.forEach(entry => {
        if (entry.entryType === 'measure' && entry.name.startsWith('render_')) {
          this.metrics.renderTimes.push({
            component: entry.name.replace('render_', ''),
            duration: entry.duration,
            timestamp: Date.now()
          });
        }
      });
    });
    renderObserver.observe({ entryTypes: ['measure'] });

    // Monitor network requests
    const networkObserver = new PerformanceObserver((list) => {
      const entries = list.getEntries();
      entries.forEach(entry => {
        if (entry.entryType === 'resource') {
          this.metrics.networkRequests.push({
            url: entry.name,
            duration: entry.duration,
            size: entry.transferSize,
            timestamp: Date.now()
          });
        }
      });
    });
    networkObserver.observe({ entryTypes: ['resource'] });
  }

  startMonitoring() {
    console.log('🔍 Starting performance monitoring...');

    // Monitor memory usage
    this.memoryInterval = setInterval(() => {
      const memUsage = process.memoryUsage();
      this.metrics.memory.push({
        heapUsed: memUsage.heapUsed,
        heapTotal: memUsage.heapTotal,
        external: memUsage.external,
        timestamp: Date.now()
      });
    }, 5000);

    // Monitor CPU usage
    this.cpuInterval = setInterval(() => {
      const cpuUsage = process.cpuUsage();
      this.metrics.cpu.push({
        user: cpuUsage.user,
        system: cpuUsage.system,
        timestamp: Date.now()
      });
    }, 5000);

    // Monitor FPS (if in browser environment)
    if (typeof window !== 'undefined') {
      let lastTime = performance.now();
      let frames = 0;

      const measureFPS = () => {
        const currentTime = performance.now();
        frames++;

        if (currentTime >= lastTime + 1000) {
          const fps = Math.round((frames * 1000) / (currentTime - lastTime));
          this.metrics.fps.push({
            value: fps,
            timestamp: Date.now()
          });

          frames = 0;
          lastTime = currentTime;
        }

        requestAnimationFrame(measureFPS);
      };

      requestAnimationFrame(measureFPS);
    }

    // Log metrics periodically
    this.logInterval = setInterval(() => {
      this.logMetrics();
    }, 60000); // Log every minute
  }

  stopMonitoring() {
    clearInterval(this.memoryInterval);
    clearInterval(this.cpuInterval);
    clearInterval(this.logInterval);
    console.log('✋ Performance monitoring stopped');
  }

  logMetrics() {
    const currentMetrics = {
      timestamp: new Date().toISOString(),
      memory: this.getAverageMemory(),
      cpu: this.getAverageCPU(),
      renderTimes: this.getAverageRenderTimes(),
      networkStats: this.getNetworkStats(),
      fps: this.getAverageFPS(),
      errors: this.metrics.errors.length
    };

    // Write to log file
    fs.appendFileSync(
      this.logFile,
      JSON.stringify(currentMetrics) + '\n'
    );

    // Clear old metrics
    this.clearOldMetrics();

    // Check for performance issues
    this.checkPerformanceIssues(currentMetrics);
  }

  getAverageMemory() {
    if (this.metrics.memory.length === 0) return null;
    const sum = this.metrics.memory.reduce((acc, curr) => acc + curr.heapUsed, 0);
    return Math.round(sum / this.metrics.memory.length);
  }

  getAverageCPU() {
    if (this.metrics.cpu.length === 0) return null;
    const sum = this.metrics.cpu.reduce((acc, curr) => acc + curr.user, 0);
    return Math.round(sum / this.metrics.cpu.length);
  }

  getAverageRenderTimes() {
    const renderTimes = {};
    this.metrics.renderTimes.forEach(entry => {
      if (!renderTimes[entry.component]) {
        renderTimes[entry.component] = [];
      }
      renderTimes[entry.component].push(entry.duration);
    });

    return Object.entries(renderTimes).reduce((acc, [component, times]) => {
      acc[component] = Math.round(times.reduce((sum, time) => sum + time, 0) / times.length);
      return acc;
    }, {});
  }

  getNetworkStats() {
    return {
      totalRequests: this.metrics.networkRequests.length,
      averageResponseTime: this.calculateAverageResponseTime(),
      totalTransferSize: this.calculateTotalTransferSize()
    };
  }

  getAverageFPS() {
    if (this.metrics.fps.length === 0) return null;
    const sum = this.metrics.fps.reduce((acc, curr) => acc + curr.value, 0);
    return Math.round(sum / this.metrics.fps.length);
  }

  calculateAverageResponseTime() {
    if (this.metrics.networkRequests.length === 0) return 0;
    const sum = this.metrics.networkRequests.reduce((acc, curr) => acc + curr.duration, 0);
    return Math.round(sum / this.metrics.networkRequests.length);
  }

  calculateTotalTransferSize() {
    return this.metrics.networkRequests.reduce((acc, curr) => acc + (curr.size || 0), 0);
  }

  clearOldMetrics() {
    const ONE_HOUR = 60 * 60 * 1000;
    const now = Date.now();

    // Keep only last hour of metrics
    Object.keys(this.metrics).forEach(key => {
      this.metrics[key] = this.metrics[key].filter(
        metric => (now - metric.timestamp) < ONE_HOUR
      );
    });
  }

  checkPerformanceIssues(metrics) {
    const issues = [];

    // Memory usage threshold (80% of total heap)
    if (metrics.memory > os.totalmem() * 0.8) {
      issues.push('High memory usage detected');
    }

    // High CPU usage threshold (90%)
    if (metrics.cpu > 90) {
      issues.push('High CPU usage detected');
    }

    // Slow render times (> 16ms for 60fps)
    Object.entries(metrics.renderTimes).forEach(([component, time]) => {
      if (time > 16) {
        issues.push(`Slow render detected in ${component}`);
      }
    });

    // Network performance issues
    if (metrics.networkStats.averageResponseTime > 1000) {
      issues.push('Slow network responses detected');
    }

    // Low FPS warning
    if (metrics.fps && metrics.fps < 30) {
      issues.push('Low FPS detected');
    }

    // Log issues if any
    if (issues.length > 0) {
      console.warn('⚠️ Performance issues detected:', issues.join(', '));
      
      // Write to separate issues log
      fs.appendFileSync(
        path.join(__dirname, '../logs/performance-issues.log'),
        `${new Date().toISOString()} - ${issues.join(', ')}\n`
      );
    }
  }

  generateReport() {
    const report = {
      timestamp: new Date().toISOString(),
      summary: {
        averageMemoryUsage: this.getAverageMemory(),
        averageCPUUsage: this.getAverageCPU(),
        totalRequests: this.metrics.networkRequests.length,
        averageResponseTime: this.calculateAverageResponseTime(),
        errorCount: this.metrics.errors.length,
        averageFPS: this.getAverageFPS()
      },
      details: {
        renderTimes: this.getAverageRenderTimes(),
        networkStats: this.getNetworkStats(),
        memoryTrend: this.metrics.memory.slice(-10), // Last 10 measurements
        cpuTrend: this.metrics.cpu.slice(-10)
      }
    };

    // Write report to file
    fs.writeFileSync(
      path.join(__dirname, '../logs/performance-report.json'),
      JSON.stringify(report, null, 2)
    );

    return report;
  }
}

// Export singleton instance
const monitor = new PerformanceMonitor();
module.exports = monitor;

// Auto-start if running directly
if (require.main === module) {
  monitor.startMonitoring();
  
  // Handle graceful shutdown
  process.on('SIGINT', () => {
    monitor.stopMonitoring();
    monitor.generateReport();
    process.exit(0);
  });
}
