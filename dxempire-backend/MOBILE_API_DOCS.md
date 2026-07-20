# 📱 Mobile App API Documentation

## Overview
Sales representatives login using their **unique Sales ID** (SM001, DM001, SG001, etc.) instead of email/password. The API returns role-specific data based on their hierarchy level.

---

## **BASE URL**
```
http://localhost:8000/api/v1/mobile
```

---

## **1. LOGIN WITH SALES ID** 🔐

### Endpoint
```
POST /auth/login
```

### Request
```json
{
  "unique_code": "SM001"
}
```

### Response (Success - 200)
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "Rajesh Kumar",
      "email": "rajesh@company.com",
      "phone": "9876543210",
      "unique_code": "SM001",
      "role": "sales"
    },
    "token": "eyJhbGc..."
  },
  "message": "Login successful"
}
```

### Response (Error - 401)
```json
{
  "success": false,
  "message": "Invalid Sales ID or account is inactive",
  "code": 401
}
```

### Usage in React Native / Flutter
```javascript
// React Native example
const login = async (uniqueCode) => {
  try {
    const response = await fetch('http://localhost:8000/api/v1/mobile/auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ unique_code: uniqueCode })
    });
    const data = await response.json();
    
    if (data.success) {
      // Save token
      await AsyncStorage.setItem('authToken', data.data.token);
      // Navigate to dashboard
      navigation.navigate('Dashboard');
    }
  } catch (error) {
    console.error(error);
  }
};
```

---

## **2. GET MY PROFILE** 👤

### Endpoint
```
GET /auth/me
```

### Headers
```
Authorization: Bearer eyJhbGc...
```

### Response (200)
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Rajesh Kumar",
    "email": "rajesh@company.com",
    "phone": "9876543210",
    "unique_code": "SM001",
    "role": "sales",
    "parent": null,
    "department": "Maharashtra"
  },
  "message": "Success"
}
```

---

## **3. GET DASHBOARD** 📊

Returns role-specific dashboard data. Different data based on user's role.

### Endpoint
```
GET /dashboard
```

### Headers
```
Authorization: Bearer eyJhbGc...
```

### Response - SALESMAN (SG001)
```json
{
  "success": true,
  "data": {
    "user_info": {
      "name": "Vikram Singh",
      "unique_code": "SG001",
      "phone": "9876543210",
      "role": "Salesman",
      "reports_to": "Amit Patel",
      "reports_to_code": "DM001"
    },
    "my_stats": {
      "total_orders": 45,
      "total_leads": 23,
      "conversion_rate": "65%",
      "month_revenue": "₹4,50,000"
    },
    "quick_actions": [
      "create_lead",
      "create_order",
      "view_orders",
      "view_leads",
      "update_profile"
    ],
    "recent_orders": [...],
    "recent_leads": [...]
  }
}
```

### Response - DISTRICT MANAGER (DM001)
```json
{
  "success": true,
  "data": {
    "user_info": {
      "name": "Amit Patel",
      "unique_code": "DM001",
      "phone": "9876543210",
      "role": "District Manager",
      "territory": "Dadar District",
      "reports_to": "Priya Singh",
      "reports_to_code": "AM001"
    },
    "team_info": {
      "total_team_members": 5,
      "direct_reports": 3,
      "team_members": [
        {
          "id": 10,
          "name": "Vikram Singh",
          "unique_code": "SG001",
          "role": "salesman",
          "is_active": true
        },
        {
          "id": 11,
          "name": "Suresh Patel",
          "unique_code": "SG002",
          "role": "salesman",
          "is_active": true
        },
        {
          "id": 12,
          "name": "Rani Sharma",
          "unique_code": "SG003",
          "role": "salesman",
          "is_active": true
        }
      ]
    },
    "team_stats": {
      "total_orders": 150,
      "total_leads": 89,
      "team_revenue": "₹15,00,000",
      "average_conversion": "72%"
    },
    "my_stats": {
      "my_orders": 20,
      "my_leads": 12
    },
    "quick_actions": [
      "view_team",
      "view_team_orders",
      "view_team_leads",
      "view_team_performance",
      "create_lead",
      "create_order"
    ],
    "team_performance": [...],
    "recent_team_orders": [...]
  }
}
```

### Response - AREA MANAGER (AM001)
```json
{
  "success": true,
  "data": {
    "user_info": {
      "name": "Priya Singh",
      "unique_code": "AM001",
      "phone": "9876543210",
      "role": "Area Manager",
      "zone": "Mumbai Zone",
      "reports_to": "Rajesh Kumar",
      "reports_to_code": "SM001"
    },
    "zone_info": {
      "total_zone_members": 15,
      "district_managers": 2,
      "salesmen": 10
    },
    "zone_stats": {
      "total_orders": 450,
      "total_leads": 250,
      "zone_revenue": "₹45,00,000",
      "zone_conversion": "75%"
    },
    "quick_actions": [
      "view_zone",
      "view_zone_orders",
      "view_zone_leads",
      "view_zone_performance",
      "manage_district_managers"
    ],
    "zone_performance": [
      { "dm_name": "Amit Patel", "dm_code": "DM001", "orders": 150 },
      { "dm_name": "Zara Khan", "dm_code": "DM002", "orders": 120 }
    ],
    "top_salesmen": [...]
  }
}
```

### Response - STATE MANAGER (SM001)
```json
{
  "success": true,
  "data": {
    "user_info": {
      "name": "Rajesh Kumar",
      "unique_code": "SM001",
      "phone": "9876543210",
      "role": "State Manager",
      "state": "Maharashtra",
      "reports_to": "CEO"
    },
    "state_info": {
      "total_state_members": 40,
      "area_managers": 3
    },
    "state_stats": {
      "total_orders": 1500,
      "total_leads": 800,
      "state_revenue": "₹1,50,00,000",
      "state_conversion": "78%"
    },
    "quick_actions": [
      "view_state_structure",
      "view_state_orders",
      "view_state_leads",
      "view_state_performance",
      "manage_area_managers"
    ],
    "area_performance": [...],
    "top_district_managers": [...]
  }
}
```

---

## **4. GET MY TEAM / SUBORDINATES** 👥

### Endpoint
```
GET /hierarchy/subordinates
```

### Headers
```
Authorization: Bearer eyJhbGc...
```

### Response
```json
{
  "success": true,
  "data": {
    "total_subordinates": 5,
    "subordinates": [
      {
        "id": 10,
        "name": "Vikram Singh",
        "unique_code": "SG001",
        "email": "vikram@company.com",
        "phone": "9876543210",
        "role": "salesman",
        "is_active": true
      },
      {
        "id": 11,
        "name": "Suresh Patel",
        "unique_code": "SG002",
        "email": "suresh@company.com",
        "phone": "9876543211",
        "role": "salesman",
        "is_active": true
      }
    ]
  },
  "message": "Success"
}
```

---

## **5. GET HIERARCHY TREE** 🌳

Shows complete organizational structure under logged-in user.

### Endpoint
```
GET /hierarchy/tree
```

### Headers
```
Authorization: Bearer eyJhbGc...
```

### Response (DM001's tree)
```json
{
  "success": true,
  "data": {
    "id": 5,
    "name": "Amit Patel",
    "unique_code": "DM001",
    "role": "district_manager",
    "subordinates": [
      {
        "id": 10,
        "name": "Vikram Singh",
        "unique_code": "SG001",
        "role": "salesman",
        "subordinates": []
      },
      {
        "id": 11,
        "name": "Suresh Patel",
        "unique_code": "SG002",
        "role": "salesman",
        "subordinates": []
      }
    ]
  },
  "message": "Success"
}
```

---

## **6. GET TEAM STATISTICS** 📈

### Endpoint
```
GET /hierarchy/team-stats
```

### Headers
```
Authorization: Bearer eyJhbGc...
```

### Response
```json
{
  "success": true,
  "data": {
    "total_team_size": 5,
    "by_role": {
      "salesman": 3,
      "district_manager": 0
    },
    "direct_reports": 3,
    "total_orders": 150,
    "total_leads": 89
  },
  "message": "Success"
}
```

---

## **7. GET COLLEAGUES** 🤝

Get other people at the same level (e.g., other District Managers under same Area Manager).

### Endpoint
```
GET /hierarchy/colleagues
```

### Headers
```
Authorization: Bearer eyJhbGc...
```

### Response
```json
{
  "success": true,
  "data": {
    "total_colleagues": 2,
    "colleagues": [
      {
        "id": 6,
        "name": "Zara Khan",
        "unique_code": "DM002",
        "role": "district_manager"
      },
      {
        "id": 7,
        "name": "Sunil Verma",
        "unique_code": "DM003",
        "role": "district_manager"
      }
    ]
  },
  "message": "Success"
}
```

---

## **8. LOGOUT** 🚪

### Endpoint
```
POST /auth/logout
```

### Headers
```
Authorization: Bearer eyJhbGc...
```

### Response (200)
```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

---

## **MOBILE APP FLOW** 📱

### Step 1: Login Screen
```
┌─────────────────────────────┐
│   Welcome to Sales App      │
│                             │
│  Enter Your Sales ID        │
│  ┌───────────────────────┐  │
│  │  SM001                │  │
│  └───────────────────────┘  │
│                             │
│    [LOGIN BUTTON]           │
└─────────────────────────────┘
```

**User enters:** SM001  
**App calls:** POST /auth/login  
**Server responds:** Token + User data

### Step 2: Dashboard (State Manager SM001)
```
┌─────────────────────────────┐
│  📊 STATE DASHBOARD         │
├─────────────────────────────┤
│                             │
│  Welcome, Rajesh Kumar!     │
│  State: Maharashtra         │
│                             │
│  STATS                      │
│  ├─ 1,500 Orders           │
│  ├─ 800 Leads              │
│  ├─ ₹1,50,00,000 Revenue   │
│  └─ 78% Conversion         │
│                             │
│  QUICK ACTIONS              │
│  [View State] [View Orders] │
│  [Manage Area Mgrs] [Perf]  │
│                             │
│  MY TEAM (3 Area Managers)  │
│  ├─ Priya Singh (AM001)     │
│  ├─ Ramesh Desai (AM002)    │
│  └─ Other (AM003)           │
│                             │
│  AREA PERFORMANCE           │
│  ├─ Mumbai: 450 orders      │
│  ├─ Pune: 380 orders        │
│  └─ Nagpur: 320 orders      │
│                             │
│  [Settings] [Logout]        │
└─────────────────────────────┘
```

### Step 3: Team View (District Manager DM001)
```
┌─────────────────────────────┐
│  👥 MY TEAM                 │
├─────────────────────────────┤
│                             │
│  Total Team: 5 members      │
│  Reports To: Priya (AM001)  │
│                             │
│  TEAM STATS                 │
│  ├─ Orders: 150             │
│  ├─ Leads: 89               │
│  ├─ Revenue: ₹15,00,000     │
│  └─ Conversion: 72%         │
│                             │
│  TEAM MEMBERS               │
│  ├─ SG001 - Vikram Singh    │
│  │  Orders: 45, Leads: 23   │
│  ├─ SG002 - Suresh Patel    │
│  │  Orders: 42, Leads: 20   │
│  └─ SG003 - Rani Sharma     │
│     Orders: 40, Leads: 19   │
│                             │
│  [View Hierarchy] [Export]  │
└─────────────────────────────┘
```

### Step 4: My Performance (Salesman SG001)
```
┌─────────────────────────────┐
│  📈 MY PERFORMANCE          │
├─────────────────────────────┤
│                             │
│  Vikram Singh (SG001)       │
│  Reports To: Amit (DM001)   │
│                             │
│  MY STATS                   │
│  ├─ Orders: 45              │
│  ├─ Leads: 23               │
│  ├─ Revenue: ₹4,50,000      │
│  └─ Conversion: 65%         │
│                             │
│  MONTHLY TREND              │
│  ┌──────────────────────┐   │
│  │    May  Jun  Jul     │   │
│  │     /\   /\   /\     │   │
│  │    /  \ /  \ /  \    │   │
│  └──────────────────────┘   │
│                             │
│  RECENT ORDERS (Top 5)      │
│  ├─ ORD-001: ₹50,000        │
│  ├─ ORD-002: ₹45,000        │
│  └─ ...                     │
│                             │
│  [Create Lead] [Create Ord] │
└─────────────────────────────┘
```

---

## **PERMISSION MATRIX** 🔐

| Feature | Salesman | DM | AM | SM | CEO |
|---------|----------|----|----|----|----|
| See own data | ✅ | ✅ | ✅ | ✅ | ✅ |
| See subordinates | ❌ | ✅ | ✅ | ✅ | ✅ |
| See own team orders | ❌ | ✅ | ✅ | ✅ | ✅ |
| See own team leads | ❌ | ✅ | ✅ | ✅ | ✅ |
| See full hierarchy | ❌ | ❌ | ✅ | ✅ | ✅ |
| See all company data | ❌ | ❌ | ❌ | ❌ | ✅ |

---

## **ERROR HANDLING** ⚠️

### Invalid Sales ID
```json
{
  "success": false,
  "message": "Invalid Sales ID or account is inactive",
  "code": 401
}
```

### Unauthorized (No Token)
```json
{
  "success": false,
  "message": "Unauthenticated",
  "code": 401
}
```

### Token Expired
```json
{
  "success": false,
  "message": "Unauthenticated",
  "code": 401
}
```

### Not Found
```json
{
  "success": false,
  "message": "Resource not found",
  "code": 404
}
```

---

## **IMPLEMENTATION CHECKLIST** ✅

- [x] POST /auth/login - Login with Sales ID
- [x] GET /auth/me - Get current user
- [x] POST /auth/logout - Logout
- [x] GET /dashboard - Role-specific dashboard
- [x] GET /hierarchy/subordinates - Get team members
- [x] GET /hierarchy/tree - Get hierarchy tree
- [x] GET /hierarchy/team-stats - Get team stats
- [x] GET /hierarchy/colleagues - Get colleagues
- [ ] TODO: Integration with Orders module
- [ ] TODO: Integration with Leads module
- [ ] TODO: Real-time push notifications
- [ ] TODO: Mobile-optimized order creation
- [ ] TODO: Mobile-optimized lead creation

---

## **EXAMPLE MOBILE APP CODE**

### Login Implementation
```javascript
import axios from 'axios';
import AsyncStorage from '@react-native-async-storage/async-storage';

const API_URL = 'http://localhost:8000/api/v1/mobile';

export const loginWithSalesID = async (uniqueCode) => {
  try {
    const response = await axios.post(`${API_URL}/auth/login`, {
      unique_code: uniqueCode,
    });

    const { token, user } = response.data.data;

    // Save token
    await AsyncStorage.setItem('authToken', token);
    await AsyncStorage.setItem('user', JSON.stringify(user));

    return { success: true, user, token };
  } catch (error) {
    return {
      success: false,
      message: error.response?.data?.message || 'Login failed',
    };
  }
};

// Get dashboard with auth
export const getDashboard = async (token) => {
  try {
    const response = await axios.get(`${API_URL}/dashboard`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    return response.data.data;
  } catch (error) {
    throw error;
  }
};

// Get team
export const getSubordinates = async (token) => {
  try {
    const response = await axios.get(`${API_URL}/hierarchy/subordinates`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    return response.data.data.subordinates;
  } catch (error) {
    throw error;
  }
};
```

---

**✅ All APIs are ready for mobile development!**
