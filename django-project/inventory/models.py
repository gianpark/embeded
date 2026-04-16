from django.db import models

class Category(models.Model):
    name = models.CharField(max_length=100, verbose_name='카테고리명')
    created_at = models.DateTimeField(auto_now_add=True)
    def __str__(self):
        return self.name

class Product(models.Model):
    category = models.ForeignKey(Category, on_delete=models.SET_NULL, null=True, blank=True)
    name = models.CharField(max_length=200, verbose_name='상품명')
    price = models.IntegerField(verbose_name='단가')
    stock = models.IntegerField(default=0, verbose_name='재고수량')
    description = models.TextField(blank=True)
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)
    def __str__(self):
        return self.name

class Order(models.Model):
    STATUS_CHOICES = [
        ('pending',   '처리대기'),
        ('confirmed', '주문확인'),
        ('shipping',  '배송중'),
        ('completed', '완료'),
        ('cancelled', '취소'),
    ]
    customer_name  = models.CharField(max_length=100)
    customer_email = models.EmailField()
    status         = models.CharField(max_length=20, choices=STATUS_CHOICES, default='pending')
    total_price    = models.IntegerField(default=0)
    note           = models.TextField(blank=True)
    created_at     = models.DateTimeField(auto_now_add=True)
    updated_at     = models.DateTimeField(auto_now=True)

    class Meta:
        ordering = ['-created_at']

    def __str__(self):
        return f'주문#{self.pk} - {self.customer_name}'

    def calc_total(self):
        total = sum(item.subtotal for item in self.orderitem_set.all())
        self.total_price = total
        self.save()

class OrderItem(models.Model):
    order    = models.ForeignKey(Order, on_delete=models.CASCADE)
    product  = models.ForeignKey(Product, on_delete=models.CASCADE)
    quantity = models.IntegerField()
    price    = models.IntegerField()

    @property
    def subtotal(self):
        return self.price * self.quantity
