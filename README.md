# ScandiPWA_CompareGraphQl

**CompareGraphQl** provides resolvers for product comparison by their attributes, extending default magento compare. 


### addProductToCompare

This endpoint allows to add item to compare

```graphql
mutation  addProductToCompare(product_sku: String!) {
    entity_id
    name
    store_id
    sku
}
```

```json
{
   "addProductToCompare": {
         "entity_id": 2517,
         "name": "Khaki Straight Leg Jeans Cadiz",
         "store_id": 1,
         "sku": "n31253494"
       }
}
```


### RemoveCompareProduct

This endpoint allows removing item from compare

```graphql
mutation removeCompareProduct(product_sku: String!) {
    removeCompareProduct(product_sku: String!)
}
```

```json
{
   "product_sku": n31253494
}
```

### ClearCompareProducts

This endpoint allows to clear compare product list

```graphql
mutation clearCompareProducts {
    clearCompareProducts()
}
```
