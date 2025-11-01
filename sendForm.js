document.addEventListener('DOMContentLoaded', () => {
    // ставим метку времени РАНО (на рендере)
    const ts = Math.floor(Date.now() / 1000);
    const tsInput = document.getElementById('form_ts');
    if (tsInput) tsInput.value = ts;
  });
  
  document.getElementById('contactForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.currentTarget;
    const fd = new FormData(form);
  
    // простая локальная проверка длины сообщения
    const msg = (fd.get('message') || '').toString().trim();
    if (msg.length < 5) {
      alert('Сообщение слишком короткое (минимум 5 символов).');
      return;
    }
  
    const res = await fetch(form.action, {
      method: 'POST',
      body: (function(){
        // Приложим данные калькулятора, если использовался
        try {
          if (window.calcSelection && window.calcSelection.used) {
            fd.set('calc_used', '1');
            fd.set('calc_program', String(window.calcSelection.program ?? ''));
            fd.set('calc_base_cost', String(window.calcSelection.baseCost ?? ''));
            fd.set('calc_children', String(window.calcSelection.children ?? ''));
            fd.set('calc_child_cost_per', String(window.calcSelection.childCostPer ?? ''));
            fd.set('calc_total', String(window.calcSelection.total ?? ''));
          } else {
            fd.set('calc_used', '0');
          }
        } catch (e) { /* ignore */ }
        return fd;
      })(),
      headers: { 'Accept': 'application/json' },
    });
    const data = await res.json();
    console.log(data);
    if (data.ok) {
      alert('Спасибо! Заявка отправлена.');
      form.reset();
      // обновим form_ts после очистки формы
      document.getElementById('form_ts').value = Math.floor(Date.now() / 1000);
    } else {
      alert('Ошибка: ' + (data.errors || []).join(', '));
    }
  });
  