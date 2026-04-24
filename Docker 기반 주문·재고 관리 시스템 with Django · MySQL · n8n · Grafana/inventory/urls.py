from django.urls import path
from . import views

urlpatterns = [
    path('',                          views.dashboard,      name='dashboard'),
    path('products/',                 views.product_list,   name='product_list'),
    path('products/new/',             views.product_create, name='product_create'),
    path('products/<int:pk>/edit/',   views.product_edit,   name='product_edit'),
    path('products/<int:pk>/delete/', views.product_delete, name='product_delete'),
    path('orders/',                   views.order_list,     name='order_list'),
    path('orders/new/',               views.order_create,   name='order_create'),
    path('orders/<int:pk>/',          views.order_detail,   name='order_detail'),
    path('orders/<int:pk>/status/',   views.order_status,   name='order_status'),
]
