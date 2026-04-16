from django.shortcuts import render, redirect, get_object_or_404
from django.contrib import messages
from django.db.models import Q
from django.http import JsonResponse
from .models import Product, Order, OrderItem, Category
import datetime

def dashboard(request):
    today = datetime.date.today()
    context = {
        'total_products': Product.objects.count(),
        'low_stock':      Product.objects.filter(stock__lte=5).count(),
        'out_stock':      Product.objects.filter(stock=0).count(),
        'total_orders':   Order.objects.count(),
        'pending_orders': Order.objects.filter(status='pending').count(),
        'today_orders':   Order.objects.filter(created_at__date=today).count(),
        'recent_orders':  Order.objects.all()[:5],
        'low_products':   Product.objects.filter(stock__lte=5).order_by('stock')[:5],
    }
    return render(request, 'inventory/dashboard.html', context)

def product_list(request):
    q = request.GET.get('q', '')
    products = Product.objects.select_related('category').all()
    if q:
        products = products.filter(Q(name__icontains=q))
    return render(request, 'inventory/product_list.html', {'products': products, 'q': q})

def product_create(request):
    categories = Category.objects.all()
    if request.method == 'POST':
        p = request.POST
        cat_id = p.get('category')
        product = Product.objects.create(
            name=p['name'],
            price=int(p['price']),
            stock=int(p['stock']),
            description=p.get('description', ''),
            category=Category.objects.get(pk=cat_id) if cat_id else None
        )
        messages.success(request, f'상품 "{product.name}" 이 등록되었습니다.')
        return redirect('product_list')
    return render(request, 'inventory/product_form.html', {'categories': categories, 'action': '등록'})

def product_edit(request, pk):
    product = get_object_or_404(Product, pk=pk)
    categories = Category.objects.all()
    if request.method == 'POST':
        p = request.POST
        cat_id = p.get('category')
        product.name = p['name']
        product.price = int(p['price'])
        product.stock = int(p['stock'])
        product.description = p.get('description', '')
        product.category = Category.objects.get(pk=cat_id) if cat_id else None
        product.save()
        messages.success(request, f'상품 "{product.name}" 이 수정되었습니다.')
        return redirect('product_list')
    return render(request, 'inventory/product_form.html', {'product': product, 'categories': categories, 'action': '수정'})

def product_delete(request, pk):
    product = get_object_or_404(Product, pk=pk)
    if request.method == 'POST':
        name = product.name
        product.delete()
        messages.success(request, f'상품 "{name}" 이 삭제되었습니다.')
    return redirect('product_list')

def order_list(request):
    status = request.GET.get('status', '')
    orders = Order.objects.prefetch_related('orderitem_set__product').all()
    if status:
        orders = orders.filter(status=status)
    return render(request, 'inventory/order_list.html', {
        'orders': orders, 'status': status,
        'status_choices': Order.STATUS_CHOICES
    })

def order_create(request):
    products = Product.objects.filter(stock__gt=0)
    if request.method == 'POST':
        p = request.POST
        order = Order.objects.create(
            customer_name=p['customer_name'],
            customer_email=p['customer_email'],
            note=p.get('note', '')
        )
        product_ids = request.POST.getlist('product_id')
        quantities  = request.POST.getlist('quantity')
        for pid, qty in zip(product_ids, quantities):
            qty = int(qty)
            if qty <= 0:
                continue
            product = Product.objects.get(pk=pid)
            OrderItem.objects.create(order=order, product=product, quantity=qty, price=product.price)
            product.stock -= qty
            product.save()
        order.calc_total()
        messages.success(request, f'주문#{order.pk} 이 등록되었습니다.')
        return redirect('order_list')
    return render(request, 'inventory/order_form.html', {'products': products})

def order_status(request, pk):
    order = get_object_or_404(Order, pk=pk)
    if request.method == 'POST':
        order.status = request.POST['status']
        order.save()
        messages.success(request, f'주문#{order.pk} 상태가 변경되었습니다.')
    return redirect('order_list')

def order_detail(request, pk):
    order = get_object_or_404(Order, pk=pk)
    items = order.orderitem_set.select_related('product').all()
    return render(request, 'inventory/order_detail.html', {
        'order': order, 'items': items,
        'status_choices': Order.STATUS_CHOICES
    })