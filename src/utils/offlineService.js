/**
 * Offline Service Utility
 * handles caching of data (products/categories) and management of the pending orders queue.
 */

const STORAGE_KEYS = {
  PRODUCTS: 'arpege_cached_products',
  CATEGORIES: 'arpege_cached_categories',
  PENDING_ORDERS: 'arpege_pending_orders',
};

export const offlineService = {
  // Caching Data
  saveData(key, data) {
    try {
      localStorage.setItem(STORAGE_KEYS[key.toUpperCase()] || key, JSON.stringify(data));
    } catch (e) {
      console.error('OfflineService: Error saving data', e);
    }
  },

  getData(key) {
    try {
      const data = localStorage.getItem(STORAGE_KEYS[key.toUpperCase()] || key);
      return data ? JSON.parse(data) : null;
    } catch (e) {
      console.error('OfflineService: Error getting data', e);
      return null;
    }
  },

  // Queue Management
  getQueue() {
    return this.getData('PENDING_ORDERS') || [];
  },

  queueOrder(order) {
    const queue = this.getQueue();
    // Add unique ID to order for tracking
    const orderWithId = {
      ...order,
      offlineId: `off_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`,
      queuedAt: new Date().toISOString()
    };
    queue.push(orderWithId);
    this.saveData('PENDING_ORDERS', queue);
    return orderWithId;
  },

  removeFromQueue(offlineId) {
    const queue = this.getQueue();
    const newQueue = queue.filter(item => item.offlineId !== offlineId);
    this.saveData('PENDING_ORDERS', newQueue);
  },

  // Sync Logic
  async syncOrders(apiUrl) {
    const queue = this.getQueue();
    if (queue.length === 0) return { success: true, count: 0 };

    console.log(`OfflineService: Attempting to sync ${queue.length} orders...`);
    let syncedCount = 0;
    const errors = [];

    // Process orders one by one to ensure sequence and reliability
    for (const order of [...queue]) {
      try {
        const response = await fetch(apiUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(order)
        });

        if (response.ok) {
          this.removeFromQueue(order.offlineId);
          syncedCount++;
        } else {
          const errorData = await response.json().catch(() => ({}));
          errors.push({ id: order.offlineId, error: errorData.message || 'Server error' });
        }
      } catch (e) {
        console.error('OfflineService: Sync error', e);
        errors.push({ id: order.offlineId, error: e.message });
        break; // Stop syncing if connection is lost again
      }
    }

    return {
      success: errors.length === 0,
      syncedCount,
      errors
    };
  }
};
