# POS System Architecture
## Laravel Backend + Electron Desktop Frontend

---

## 1. Overall Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                     ELECTRON DESKTOP APP                        │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │ Renderer Process                                            │ │
│  │ • React/Vue.js UI                                          │ │
│  │ • Redux/Pinia state management                             │ │
│  │ • Local SQLite/IndexedDB (offline storage)                 │ │
│  └────────────────────────────────────────────────────────────┘ │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │ Main Process                                                │ │
│  │ • IPC handlers for file operations                         │ │
│  │ • Sync service (online/offline logic)                      │ │
│  │ • HTTP client middleware                                   │ │
│  └────────────────────────────────────────────────────────────┘ │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │ Local Database (SQLite/LevelDB)                            │ │
│  │ • Cache of remote data                                     │ │
│  │ • Queue of unsent transactions                             │ │
│  └────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
                               ↕ HTTP/WebSocket
                    (REST API + Real-time updates)
┌─────────────────────────────────────────────────────────────────┐
│              LARAVEL BACKEND API (REST + WebSocket)             │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │ Controllers/Routes                                         │ │
│  │ • Authentication (Sanctum tokens)                          │ │
│  │ • Products, Transactions, Inventory                        │ │
│  │ • Reports, Analytics                                       │ │
│  └────────────────────────────────────────────────────────────┘ │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │ Services/Business Logic                                    │ │
│  │ • Transaction processing                                   │ │
│  │ • Inventory sync                                           │ │
│  │ • Conflict resolution                                      │ │
│  └────────────────────────────────────────────────────────────┘ │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │ Database (MySQL/PostgreSQL)                                │ │
│  │ • Users, Products, Transactions                            │ │
│  │ • Sync metadata (versions, timestamps)                     │ │
│  └────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
```

---

## 2. Technology Stack

| Layer | Technology | Purpose |
|-------|-----------|---------|
| **Frontend** | Electron + React | Desktop app with native feel |
| **State Mgmt** | Redux Toolkit | Predictable state management |
| **Local DB** | SQLite + Better-SQLite3 | Offline-first data storage |
| **Backend** | Laravel 11 | REST API & WebSocket server |
| **Authentication** | Laravel Sanctum | Token-based API auth |
| **Database** | PostgreSQL (recommended) | Scalable, reliable data store |
| **Queue** | Redis + Laravel Queue | Async job processing |
| **Real-time** | Laravel WebSocket or Pusher | Live inventory/transaction updates |
| **HTTP Client** | Axios (Electron) + Guzzle (Laravel) | API communication |

---

## 3. How Electron Communicates with Laravel

### 3.1 REST API Communication Flow

```javascript
// Electron Main Process (Sync Service)
// Built-in HTTP client middleware with offline support

class ApiClient {
  async request(endpoint, options = {}) {
    try {
      // Check if online
      if (!this.isOnline()) {
        return this.handleOfflineRequest(endpoint, options);
      }

      // Online request
      const response = await axios.post(`${BACKEND_URL}${endpoint}`, options.data, {
        headers: {
          'Authorization': `Bearer ${this.accessToken}`,
          'Content-Type': 'application/json',
        }
      });

      // On success, sync to local DB
      await this.syncToLocalDB(endpoint, response.data);
      return response.data;
    } catch (error) {
      if (error.response?.status === 401) {
        await this.refreshToken();
      }
      // Queue for retry
      await this.queueForSync(endpoint, options);
      throw error;
    }
  }
}
```

### 3.2 IPC Communication (Electron Inter-Process)

```javascript
// Main Process to Renderer Process
ipcMain.handle('api:get-products', async () => {
  try {
    const data = await apiClient.request('/api/products');
    return { success: true, data };
  } catch (error) {
    return { success: false, error: error.message };
  }
});

// Renderer Process
const products = await ipcRenderer.invoke('api:get-products');
```

### 3.3 WebSocket for Real-time Updates

```javascript
// Electron subscribes to store channel via WebSocket
import io from 'socket.io-client';

const socket = io(`${BACKEND_URL}`, {
  auth: {
    token: accessToken,
  }
});

// Listen for inventory updates
socket.on('inventory:updated', (payload) => {
  store.commit('updateInventory', payload);
});

// Listen for transactions from other registers
socket.on('transaction:created', (payload) => {
  store.commit('addTransaction', payload);
});
```

---

## 4. Sanctum Authentication Architecture

### 4.1 Authentication Flow

```
┌─────────────┐
│  Electron   │
└──────┬──────┘
       │ POST /api/login
       │ { email, password }
       ▼
┌──────────────────────┐
│  Laravel Sanctum     │
│ ✓ Validate password  │
│ ✓ Generate token     │
└──────┬───────────────┘
       │ Returns { token, user }
       ▼
┌─────────────────────────┐
│  Electron stores token  │
│  - Secure storage      │
│  - Memory (runtime)    │
└─────────────────────────┘
       │ All future requests
       │ Authorization: Bearer {token}
       ▼
┌──────────────────────────┐
│  Laravel Sanctum         │
│ ✓ Validate token        │
│ ✓ Check rate limit      │
│ ✓ Authenticate request  │
└──────────────────────────┘
```

### 4.2 Laravel Setup

```php
// config/sanctum.php
'stateful' => explode(',', env(
    'SANCTUM_STATEFUL_DOMAINS',
    'localhost,127.0.0.1,127.0.0.1:3000'
)),

'api_middleware' => [
    EnsureFrontendRequestsAreStateful::class,
    'throttle:60,1', // Rate limiting
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
],
```

```php
// app/Http/Controllers/AuthController.php
public function login(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    if (!Auth::attempt($request->only('email', 'password'))) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    $user = Auth::user();
    $token = $user->createToken('pos-app')->plainTextToken;

    return response()->json([
        'token' => $token,
        'user' => $user,
        'store_id' => $user->store_id,
    ]);
}

public function logout(Request $request)
{
    $request->user()->currentAccessToken()->delete();
    return response()->json(['message' => 'Logged out']);
}
```

### 4.3 Electron Token Management

```javascript
// electron/main/tokenManager.ts
import keytar from 'keytar';

class TokenManager {
  async saveToken(token) {
    // Store in secure OS keychain
    await keytar.setPassword('pos-app', 'access_token', token);
  }

  async getToken() {
    return await keytar.getPassword('pos-app', 'access_token');
  }

  async clearToken() {
    await keytar.deletePassword('pos-app', 'access_token');
  }
}
```

---

## 5. Offline/Online Mode Strategy

### 5.1 Offline-First Data Model

```
LOCAL DATABASE (SQLite)
├── Products (synced, read-only when offline)
├── Transactions (local-first, queued when offline)
├── Queue (unsent API requests)
├── Metadata (sync timestamps, versions)
└── Inventory Cache (last known state)
```

### 5.2 Sync Service Architecture

```javascript
// electron/main/syncService.ts
class SyncService {
  constructor(apiClient, localDb) {
    this.apiClient = apiClient;
    this.localDb = localDb;
    this.isOnline = navigator.onLine;
    
    window.addEventListener('online', () => this.goOnline());
    window.addEventListener('offline', () => this.goOffline());
  }

  async goOnline() {
    this.isOnline = true;
    // Priority: Process queued transactions first
    await this.processSyncQueue();
    // Then sync data
    await this.syncDataFromServer();
  }

  async processSyncQueue() {
    const queue = await this.localDb.getQueue();
    
    for (const item of queue) {
      try {
        await this.apiClient.request(item.endpoint, item.options);
        await this.localDb.removeFromQueue(item.id);
      } catch (error) {
        // Keep in queue for retry
        console.error('Sync failed:', item.id, error);
      }
    }
  }

  async syncDataFromServer() {
    try {
      // Fetch products (changed since last sync)
      const lastSync = await this.localDb.getLastSync('products');
      const products = await this.apiClient.request(
        `/api/products?since=${lastSync}`
      );
      await this.localDb.bulkUpsert('products', products);

      // Fetch transactions
      const transactions = await this.apiClient.request('/api/transactions');
      await this.localDb.bulkUpsert('transactions', transactions);

      await this.localDb.setLastSync('products', Date.now());
    } catch (error) {
      console.error('Sync from server failed:', error);
    }
  }
}
```

### 5.3 Transaction Handling (Offline-Safe)

```php
// Laravel: Transaction endpoint with idempotency
class TransactionController extends Controller
{
    public function store(StoreTransactionRequest $request)
    {
        $idempotencyKey = $request->header('Idempotency-Key');
        
        // Check if transaction already processed
        $existing = Transaction::where(
            'idempotency_key',
            $idempotencyKey
        )->first();
        
        if ($existing) {
            return response()->json($existing);
        }

        $transaction = DB::transaction(function () use ($request, $idempotencyKey) {
            $transaction = Transaction::create([
                'idempotency_key' => $idempotencyKey,
                'store_id' => auth()->user()->store_id,
                'total' => $request->total,
                'status' => 'completed',
                'created_at' => $request->created_at, // Client timestamp
            ]);

            // Update inventory
            foreach ($request->items as $item) {
                InventoryLog::create([
                    'product_id' => $item['product_id'],
                    'quantity' => -$item['quantity'],
                    'transaction_id' => $transaction->id,
                ]);
            }

            return $transaction;
        });

        return response()->json($transaction);
    }
}
```

```javascript
// Electron: Create transaction with idempotency
async createTransaction(items) {
  const transactionId = uuidv4();
  const transaction = {
    id: transactionId,
    items,
    total: calculateTotal(items),
    created_at: new Date().toISOString(),
  };

  // Save to local DB immediately
  await localDb.insert('transactions', transaction);

  if (this.isOnline) {
    try {
      // Send to server with idempotency key
      await this.apiClient.request('/api/transactions', {
        data: transaction,
        headers: {
          'Idempotency-Key': transactionId,
        }
      });
    } catch (error) {
      // Will retry from sync queue
      console.error('Transaction sync failed, will retry');
    }
  }

  return transaction;
}
```

### 5.4 Conflict Resolution

```javascript
// If server version differs from local version
async resolveConflict(entity, localVersion, serverVersion) {
  // Strategy: Server always wins for inventory
  if (entity === 'inventory') {
    await localDb.update(entity, serverVersion);
    return serverVersion;
  }

  // Strategy: Merge transactions (both are valid)
  if (entity === 'transactions') {
    if (!serverVersion.exists(localVersion.id)) {
      // Local transaction doesn't exist on server yet
      await apiClient.sync(localVersion);
    }
    return { ...serverVersion, ...localVersion };
  }
}
```

---

## 6. Database Schema (PostgreSQL Recommended)

### 6.1 Why PostgreSQL over MySQL?

| Feature | PostgreSQL | MySQL |
|---------|-----------|-------|
| JSONB Support | ✓ (native) | Partial |
| Full-Text Search | ✓ (advanced) | Basic |
| Transactions | ✓ (ACID) | ✓ (ACID) |
| Scalability | ✓ Better for large data | Good |
| Concurrency | ✓ Better | Good |
| Cost | Free (Open Source) | Free (Open Source) |

### 6.2 Core Schema

```sql
-- Users & Authentication
CREATE TABLE users (
    id BIGINT PRIMARY KEY,
    store_id BIGINT NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(255),
    role ENUM('admin', 'cashier', 'manager') DEFAULT 'cashier',
    is_active BOOLEAN DEFAULT TRUE,
    last_login_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id)
);

-- Store/Branch
CREATE TABLE stores (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    address TEXT,
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products
CREATE TABLE products (
    id BIGINT PRIMARY KEY,
    store_id BIGINT NOT NULL,
    sku VARCHAR(100) UNIQUE,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    cost DECIMAL(10, 2),
    category VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    metadata JSONB DEFAULT '{}', -- For flexibility
    version INT DEFAULT 1,
    synced_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id),
    INDEX idx_store_product (store_id, id)
);

-- Inventory
CREATE TABLE inventory (
    id BIGINT PRIMARY KEY,
    product_id BIGINT NOT NULL,
    store_id BIGINT NOT NULL,
    quantity_on_hand INT DEFAULT 0,
    quantity_reserved INT DEFAULT 0,
    reorder_level INT DEFAULT 10,
    last_counted_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (store_id) REFERENCES stores(id),
    UNIQUE (product_id, store_id)
);

-- Transactions (Receipts)
CREATE TABLE transactions (
    id BIGINT PRIMARY KEY,
    store_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    idempotency_key VARCHAR(100) UNIQUE, -- Prevent duplicates
    transaction_number VARCHAR(50) UNIQUE,
    status ENUM('pending', 'completed', 'refunded', 'cancelled') DEFAULT 'completed',
    subtotal DECIMAL(10, 2) DEFAULT 0,
    tax DECIMAL(10, 2) DEFAULT 0,
    discount DECIMAL(10, 2) DEFAULT 0,
    total DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('cash', 'card', 'other') DEFAULT 'cash',
    notes TEXT,
    synced BOOLEAN DEFAULT FALSE,
    version INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_store_transaction (store_id, created_at),
    INDEX idx_idempotency (idempotency_key)
);

-- Transaction Items
CREATE TABLE transaction_items (
    id BIGINT PRIMARY KEY,
    transaction_id BIGINT NOT NULL,
    product_id BIGINT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Inventory Audit Trail
CREATE TABLE inventory_logs (
    id BIGINT PRIMARY KEY,
    product_id BIGINT NOT NULL,
    store_id BIGINT NOT NULL,
    quantity_change INT NOT NULL,
    transaction_id BIGINT,
    reason VARCHAR(100), -- 'sale', 'adjustment', 'receive', 'damage'
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (store_id) REFERENCES stores(id),
    FOREIGN KEY (transaction_id) REFERENCES transactions(id),
    INDEX idx_store_log (store_id, created_at),
    INDEX idx_product_log (product_id, created_at)
);

-- Sync Metadata (for offline sync tracking)
CREATE TABLE sync_metadata (
    id BIGINT PRIMARY KEY,
    store_id BIGINT NOT NULL,
    entity_type VARCHAR(100), -- 'products', 'inventory', etc
    last_sync_at TIMESTAMP,
    version INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id),
    UNIQUE (store_id, entity_type)
);

-- Sanctum tokens (built-in Laravel)
CREATE TABLE personal_access_tokens (
    id BIGINT PRIMARY KEY,
    tokenable_type VARCHAR(255),
    tokenable_id BIGINT,
    name VARCHAR(255),
    token VARCHAR(64) UNIQUE,
    abilities JSON,
    last_used_at TIMESTAMP,
    expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## 7. API Endpoints

### 7.1 Authentication

```
POST   /api/auth/login           → Get access token
POST   /api/auth/logout          → Revoke token
POST   /api/auth/refresh         → Refresh token
GET    /api/auth/me              → Current user info
```

### 7.2 Products & Inventory

```
GET    /api/products             → List products
GET    /api/products/{id}        → Get product
POST   /api/products             → Create (admin only)
PUT    /api/products/{id}        → Update
GET    /api/inventory            → Get current inventory
PUT    /api/inventory/{id}       → Adjust stock
```

### 7.3 Transactions

```
POST   /api/transactions         → Create transaction (idempotent)
GET    /api/transactions         → List transactions
GET    /api/transactions/{id}    → Get receipt
POST   /api/transactions/{id}/refund → Refund
```

### 7.4 Reports

```
GET    /api/reports/sales        → Daily sales summary
GET    /api/reports/inventory    → Inventory status
GET    /api/reports/top-products → Top selling products
```

### 7.5 Real-time

```
WebSocket /socket.io
Events:
  - inventory:updated
  - transaction:created
  - user:online / user:offline
```

---

## 8. Deployment Architecture

### 8.1 Backend Deployment (Laravel)

```
┌─────────────────┐
│   Load Balancer │ (AWS ALB / nginx)
└────────┬────────┘
         │
    ┌────┴────┐
    │          │
┌───▼──┐  ┌───▼──┐
│ API  │  │ API  │ (Multiple instances, auto-scaling)
│ Srv1 │  │ Srv2 │
└────┬─┘  └───┬──┘
     │        │
     └────┬───┘
          │
    ┌─────▼─────┐
    │ PostgreSQL │ (RDS with replication)
    └───────────┘
    
    ┌──────────┐
    │  Redis   │ (Cache + Queue)
    └──────────┘
```

### 8.2 Frontend Deployment (Electron)

```
Auto-Update Flow:
Electron App → Checks electron-updater
            → Downloads new version (GitHub/S3)
            → Restarts with new version

Code signing recommended for production:
- Windows: Authenticode certificate
- macOS: Apple Developer certificate
```

---

## 9. Security Best Practices

### 9.1 Backend Security

```php
// Middleware for API security
- CORS (restrict to Electron app domain)
- Rate limiting (prevent brute force)
- CSRF protection (if needed)
- Request validation
- SQL injection prevention (Eloquent ORM)
- Password hashing (bcrypt)
- HTTPS enforced
```

### 9.2 Frontend Security

```javascript
// Electron security
- Preload scripts (IPC isolation)
- Content Security Policy
- No eval() or dynamic code execution
- Secure token storage (OS keychain)
- Auto-update signing verification
```

### 9.3 Data in Transit

```
- HTTPS/TLS 1.3 only
- Certificate pinning (optional, for extra security)
- WebSocket over WSS (secure)
```

### 9.4 Data at Rest

```
- Encrypt sensitive data (Laravel: Laravel Encryptable)
- Database password hashing
- Secure token storage in OS keychain
```

---

## 10. Local Database Schema (SQLite - Electron)

```sql
-- Synchronized from server
CREATE TABLE products (
    id TEXT PRIMARY KEY,
    name TEXT,
    price REAL,
    category TEXT,
    synced_at INTEGER
);

CREATE TABLE inventory (
    id TEXT PRIMARY KEY,
    product_id TEXT,
    quantity INTEGER,
    synced_at INTEGER
);

-- Local-first
CREATE TABLE transactions (
    id TEXT PRIMARY KEY,
    items JSON,
    total REAL,
    status TEXT, -- 'local', 'syncing', 'synced'
    created_at INTEGER
);

-- Sync queue
CREATE TABLE sync_queue (
    id TEXT PRIMARY KEY,
    endpoint TEXT,
    method TEXT, -- 'POST', 'PUT', 'DELETE'
    payload JSON,
    retry_count INTEGER DEFAULT 0,
    created_at INTEGER
);

CREATE TABLE metadata (
    key TEXT PRIMARY KEY,
    value TEXT
);
```

---

## 11. Development Workflow

### 11.1 Local Setup

```bash
# Backend
git clone <repo>
cd pos-system
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve

# Frontend
cd electron-app
npm install
npm run dev

# Tests
php artisan test
npm run test:e2e
```

### 11.2 CI/CD Pipeline

```yaml
# GitHub Actions / GitLab CI
On commit:
1. Run Laravel tests
2. Run Electron tests
3. Build Docker image (Backend)
4. Build Electron app (Windows/macOS)
5. Deploy to staging
6. Run integration tests
7. Deploy to production (manual approval)
```

---

## 12. Monitoring & Logging

### 12.1 Backend Monitoring

- **Laravel Logs**: /storage/logs/
- **Error Tracking**: Sentry, Rollbar
- **Performance**: Laravel Telescope, New Relic
- **Uptime**: UptimeRobot

### 12.2 Frontend Monitoring

- **Crash Reports**: Sentry
- **User Analytics**: Mixpanel
- **Performance**: Electron app built-in logging

---

## 13. Sample Implementation Timeline

```
Week 1-2: Setup Laravel API + Sanctum auth
Week 3: Electron setup + IPC communication
Week 4: Local SQLite + sync service
Week 5: Transaction processing
Week 6: Offline-first features + conflict resolution
Week 7: Real-time WebSocket updates
Week 8: Testing + deployment setup
```

---

## 14. Production Checklist

- [ ] Environment variables configured (.env)
- [ ] Database backups automated
- [ ] Error tracking (Sentry) configured
- [ ] API rate limiting enabled
- [ ] HTTPS/TLS certificates installed
- [ ] Electron app code signed
- [ ] Auto-update mechanism working
- [ ] Database indexes optimized
- [ ] Redis cache configured
- [ ] Load balancer configured
- [ ] Monitoring alerts set up
- [ ] Disaster recovery plan documented
- [ ] Penetration testing completed

---

## Key Takeaways

✅ **Simple Architecture**: REST + WebSocket, no GraphQL complexity  
✅ **Offline-First**: SQLite local DB with smart sync  
✅ **Security**: Sanctum tokens + OS keychain + HTTPS  
✅ **Scalable**: PostgreSQL + Redis + load balancer ready  
✅ **Production-Ready**: Proper error handling, conflict resolution, idempotency  

This design handles multi-register scenarios, offline resilience, and scaling from single to multi-store deployments.
