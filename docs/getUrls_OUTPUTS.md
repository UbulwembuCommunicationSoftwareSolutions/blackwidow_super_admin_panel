# `getUrls` Method - Complete Output Documentation

## Method Signature
```php
public function getUrls(Request $request)
```

**Route:** `POST /api/urls`  
**Request Parameter:** `app_url` (string)

---

## Possible Outputs

### 1. Success Response - Empty Array
**Scenario:** Customer found but has no subscriptions

**Status Code:** `200 OK`

**Response:**
```json
[]
```

---

### 2. Success Response - With Subscription Data
**Scenario:** Customer found with one or more subscriptions

**Status Code:** `200 OK`

**Response Format:**
```json
[
  {
    "type": "subscription_type_name",
    "url": "subscription_url"
  },
  {
    "type": "subscription_type_name",
    "url": "subscription_url"
  }
]
```

**Example Response:**
```json
[
  {
    "type": "console",
    "url": "example.com"
  },
  {
    "type": "responder",
    "url": "responder.example.com"
  },
  {
    "type": "reporter",
    "url": "reporter.example.com"
  }
]
```

**Possible `type` Values (from subscription_types.name field):**
- `console` (ID: 1)
- `firearm` (ID: 2)
- `responder` (ID: 3)
- `reporter` (ID: 4)
- `security` (ID: 5)
- `driver` (ID: 6)
- `survey` (ID: 7)
- `DONOTUSE` (ID: 8)
- `time` (ID: 9)
- `stock` (ID: 10)
- `information` (ID: 11)
- Any custom value stored in the `subscription_types.name` column

**Note:** The `type` value comes directly from `subscription_types.name` in the database, which may differ from the IDs listed above.

---

### 3. Error Response - Customer Subscription Not Found
**Scenario:** `findCustomerSubscriptionByUrl()` returns `null`

**Status Code:** `500 Internal Server Error`

**Error:**
```
Attempt to read property "customer_id" on null
```

**Line:** `45` - `$customer = Customer::find($customerSub->customer_id);`

**When this occurs:**
- No `CustomerSubscription` matches the provided `app_url` (using LIKE search)
- The `app_url` parameter is missing or invalid

---

### 4. Error Response - Customer Not Found
**Scenario:** `Customer::find()` returns `null` for the `customer_id`

**Status Code:** `500 Internal Server Error`

**Error:**
```
Attempt to read property "customerSubscriptions" on null
```

**Line:** `47` - `foreach($customer->customerSubscriptions as $subscription)`

**When this occurs:**
- The `customer_id` from the subscription doesn't exist in the customers table
- Data integrity issue (orphaned subscription record)

---

### 5. Error Response - Missing Subscription Type Relationship
**Scenario:** A `CustomerSubscription` has a null `subscriptionType` relationship

**Status Code:** `500 Internal Server Error`

**Error:**
```
Attempt to read property "name" on null
```

**Line:** `49` - `'type' => $subscription->subscriptionType->name`

**When this occurs:**
- `subscription_type_id` is null or points to a non-existent/soft-deleted `SubscriptionType`
- The subscription type was deleted (soft delete) but subscription still references it

---

## Code Flow Summary

1. **Input:** `$request->app_url` (string)
2. **Step 1:** Find `CustomerSubscription` by URL (LIKE search on cleaned URL)
   - If not found → **Error #3**
3. **Step 2:** Find `Customer` by `customer_id` from subscription
   - If not found → **Error #4**
4. **Step 3:** Loop through `customer->customerSubscriptions`
   - If any subscription has null `subscriptionType` → **Error #5**
   - If no subscriptions → **Success #1**
   - If subscriptions exist → **Success #2**

---

## Recommended Improvements

To handle errors more gracefully, consider adding null checks:

```php
public function getUrls(Request $request)
{
    $customerSub = $this->findCustomerSubscriptionByUrl($request->app_url);
    
    if (!$customerSub) {
        return response()->json(['error' => 'No subscription found for the provided URL'], 404);
    }
    
    $customer = Customer::find($customerSub->customer_id);
    
    if (!$customer) {
        return response()->json(['error' => 'Customer not found'], 404);
    }
    
    $urls = [];
    foreach($customer->customerSubscriptions as $subscription){
        // Handle null subscriptionType
        $typeName = $subscription->subscriptionType?->name ?? 'Unknown';
        
        $urls[] = [
            'type' => $typeName,
            'url' => $subscription->url
        ];
    }
    
    return response()->json($urls);
}
```
