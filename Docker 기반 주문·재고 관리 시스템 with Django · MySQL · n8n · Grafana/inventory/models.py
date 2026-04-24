from django.db import models

class Category(models.Model):
    name = models.CharField(max_length=100, verbose_name='카테고리명')
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        verbose_name = '카테고리'
        verbose_name_plural = '카테고리 목록'

    def __str__(self):
        return self.name


class Product(models.Model):
    category    = models.ForeignKey(Category, on_delete=models.SET_NULL, null=True, blank=True, verbose_name='카테고리')
    name        = models.CharField(max_length=200, verbose_name='상품명')
    price       = models.IntegerField(verbose_name='단가')
    stock       = models.IntegerField(default=0, verbose_name='재고수량')
    description = models.TextField(blank=True, verbose_name='설명')
    created_at  = models.DateTimeField(auto_now_add=True)
    updated_at  = models.DateTimeField(auto_now=True)

    class Meta:
        verbose_name = '상품'
        verbose_name_plural = '상품 목록'

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
    customer_name  = models.CharField(max_length=100, verbose_name='고객명')
    customer_email = models.EmailField(verbose_name='이메일')
    status         = models.CharField(max_length=20, choices=STATUS_CHOICES, default='pending', verbose_name='상태')
    total_price    = models.IntegerField(default=0, verbose_name='총금액')
    note           = models.TextField(blank=True, verbose_name='비고')
    created_at     = models.DateTimeField(auto_now_add=True)
    updated_at     = models.DateTimeField(auto_now=True)

    class Meta:
        verbose_name = '주문'
        verbose_name_plural = '주문 목록'
        ordering = ['-created_at']

    def __str__(self):
        return f'주문#{self.pk} - {self.customer_name}'

    def calc_total(self):
        self.total_price = sum(item.subtotal for item in self.orderitem_set.all())
        self.save()


class OrderItem(models.Model):
    order    = models.ForeignKey(Order, on_delete=models.CASCADE, verbose_name='주문')
    product  = models.ForeignKey(Product, on_delete=models.CASCADE, verbose_name='상품')
    quantity = models.IntegerField(verbose_name='수량')
    price    = models.IntegerField(verbose_name='단가')

    @property
    def subtotal(self):
        return self.price * self.quantity