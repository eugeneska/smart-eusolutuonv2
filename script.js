// Header + menu logic (robust)
document.addEventListener('DOMContentLoaded', () => {
  const header = document.querySelector('.site-header');
  const burger = document.querySelector('.burger-menu');
  const mainNav = document.querySelector('.main-nav');
  const navOverlay = document.querySelector('.nav-overlay');
  const navLinks = document.querySelectorAll('.nav-links a');
  const menuCloseBtn = document.querySelector('.menu-close-btn');

  if (!header) { console.warn('Header .site-header not found'); return; }

  // Универсальный getter скролла
  function getScrollTop() {
    return window.pageYOffset
        ?? document.documentElement.scrollTop
        ?? document.body.scrollTop
        ?? 0;
  }

  function checkScroll() {
    const y = getScrollTop();
    header.classList.toggle('scrolled', y > 50);
  }

  // rAF-троттлинг
  let ticking = false;
  function onAnyScroll() {
    if (!ticking) {
      requestAnimationFrame(() => {
        checkScroll();
        ticking = false;
      });
      ticking = true;
    }
  }

  // Подвешиваемся только к window - это основной источник скролла
  window.addEventListener('scroll', onAnyScroll, { passive: true });
  
  // Дополнительно слушаем на document для подстраховки
  document.addEventListener('scroll', onAnyScroll, { passive: true, capture: true });
  
  // Также слушаем на documentElement для старых браузеров
  document.documentElement.addEventListener('scroll', onAnyScroll, { passive: true });

  // Первичный вызов
  checkScroll();

  // --- Меню ---
  function openMenu() {
    burger?.classList.add('active');
    mainNav?.classList.add('active');
    navOverlay?.classList.add('active');
    header.classList.add('menu-open');
    document.body.style.overflow = 'hidden';
  }
  function closeMenu() {
    burger?.classList.remove('active');
    mainNav?.classList.remove('active');
    navOverlay?.classList.remove('active');
    header.classList.remove('menu-open');
    document.body.style.overflow = '';
  }

  burger?.addEventListener('click', () => {
    burger.classList.contains('active') ? closeMenu() : openMenu();
  });
  menuCloseBtn?.addEventListener('click', closeMenu);
  navOverlay?.addEventListener('click', closeMenu);

  navLinks.forEach(link => {
    link.addEventListener('click', function(e) {
      const href = this.getAttribute('href') || '';
      if (href.startsWith('#')) {
        e.preventDefault();
        const targetId = href;
        closeMenu();
        setTimeout(() => {
          const target = document.querySelector(targetId);
          if (target) {
            const navHeight = header.offsetHeight || 0;
            const top = target.getBoundingClientRect().top + (window.pageYOffset || 0) - navHeight - 8;
            window.scrollTo({ top, behavior: 'smooth' });
          } else {
            console.warn('Anchor target not found:', targetId);
          }
        }, 340);
      }
    });
  });

  // Один resize-хендлер
  window.addEventListener('resize', () => {
    checkScroll();
    if (window.innerWidth > 768) closeMenu();
  }, { passive: true });

  // Esc закрывает меню
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeMenu();
  });
});

// --- ПРЕМИАЛЬНЫЙ КАЛЬКУЛЯТОР С КНОПКОЙ И ОТКАТОМ ---
document.addEventListener('DOMContentLoaded', () => {
  // Элементы
  const programOptions = document.querySelectorAll('.program-option');
  const counterValue = document.querySelector('.counter-value');
  const minusBtn = document.querySelector('.counter-btn.minus');
  const plusBtn = document.querySelector('.counter-btn.plus');
  const btnNext = document.querySelector('.btn-next');
  const btnBack = document.querySelectorAll('.btn-back');
  const btnCalculate = document.querySelector('.btn-calculate');
  const btnConsultation = document.querySelector('.btn-consultation');
  
  const resultPlaceholder = document.querySelector('.result-placeholder');
  const resultActive = document.querySelector('.result-active');
  const circleProgress = document.querySelector('.circle-progress');
  const resultAmount = document.querySelector('.result-amount');
  const detailBase = document.querySelector('#detail-base');
  const detailChildren = document.querySelector('#detail-children');
  const detailTotal = document.querySelector('#detail-total');
  
  const summaryProgram = document.querySelector('#summary-program');
  const summaryChildren = document.querySelector('#summary-children');
  
  // Состояние
  let selectedProgram = null;
  let childrenCount = 0;
  let currentStep = 1;
  
  // Цены по умолчанию (тыс. €), могут быть переопределены настройками сайта
  let PRICE_BASE_REGULAR = 150;
  let PRICE_BASE_FAST = 250;
  let PRICE_BASE_POLAND = 310;
  let PRICE_CHILD = 20;

  // Глобальный объект для сохранения выбора калькулятора
  window.calcSelection = {
    used: false,
    program: null, // 'hungary-regular' | 'hungary-fast' | 'poland'
    baseCost: null, // тыс. €
    children: 0,
    childCostPer: null, // тыс. €
    total: null // тыс. €
  };

  function updateCalcSelectionPartial(partial) {
    window.calcSelection = Object.assign(window.calcSelection || {}, partial);
  }

  // Загружаем цены из site_settings.json (если доступны)
  fetch('/site_settings.json', { cache: 'no-store' })
    .then(r => r.ok ? r.json() : null)
    .then(s => {
      if (!s) return;
      if (Number.isFinite(+s.calc_base_regular)) PRICE_BASE_REGULAR = +s.calc_base_regular;
      if (Number.isFinite(+s.calc_base_fast)) PRICE_BASE_FAST = +s.calc_base_fast;
      if (Number.isFinite(+s.calc_base_poland)) PRICE_BASE_POLAND = +s.calc_base_poland;
      if (Number.isFinite(+s.calc_child_cost)) PRICE_CHILD = +s.calc_child_cost;
      // обновим выбранную программу, если уже выбрана, чтобы пересчет шел с новыми ценами
      const selected = document.querySelector('.program-option .option-card.selected');
      if (selected) {
        const parent = selected.closest('.program-option');
        if (parent) {
          const val = parent.dataset.value;
          if (val === 'hungary-regular' || val === 'regular') selectedProgram = PRICE_BASE_REGULAR;
          if (val === 'hungary-fast' || val === 'fast') selectedProgram = PRICE_BASE_FAST;
          if (val === 'poland') selectedProgram = PRICE_BASE_POLAND;
        }
      }
    })
    .catch(() => {});
  
  // Инициализация
  updateCounterDisplay();
  updateNextButton();
  
  // Обработчики выбора программы
  programOptions.forEach(option => {
    option.addEventListener('click', () => {
      // Снимаем выделение со всех опций
      programOptions.forEach(opt => {
        opt.querySelector('.option-card').classList.remove('selected');
      });
      
      // Выделяем выбранную опцию
      option.querySelector('.option-card').classList.add('selected');
      const datasetVal = option.dataset.value;
      if (datasetVal === 'hungary-regular' || datasetVal === 'regular' || datasetVal === '150') {
        selectedProgram = PRICE_BASE_REGULAR;
        updateCalcSelectionPartial({ used: true, program: 'hungary-regular', baseCost: PRICE_BASE_REGULAR, childCostPer: PRICE_CHILD });
      } else if (datasetVal === 'hungary-fast' || datasetVal === 'fast' || datasetVal === '250') {
        selectedProgram = PRICE_BASE_FAST;
        updateCalcSelectionPartial({ used: true, program: 'hungary-fast', baseCost: PRICE_BASE_FAST, childCostPer: PRICE_CHILD });
      } else if (datasetVal === 'poland' || datasetVal === '310') {
        selectedProgram = PRICE_BASE_POLAND;
        updateCalcSelectionPartial({ used: true, program: 'poland', baseCost: PRICE_BASE_POLAND, childCostPer: PRICE_CHILD });
      } else {
        const parsed = parseInt(datasetVal);
        selectedProgram = Number.isFinite(parsed) ? parsed : PRICE_BASE_REGULAR;
        updateCalcSelectionPartial({ used: true, program: 'hungary-regular', baseCost: selectedProgram, childCostPer: PRICE_CHILD });
      }
      
      // Активируем кнопку "Продолжить"
      updateNextButton();
    });
  });
  
  // Обработчики счетчика детей
  minusBtn.addEventListener('click', () => {
    if (childrenCount > 0) {
      childrenCount--;
      updateCounterDisplay();
      updateCalcSelectionPartial({ used: true, children: childrenCount });
    }
  });
  
  plusBtn.addEventListener('click', () => {
    childrenCount++;
    updateCounterDisplay();
    updateCalcSelectionPartial({ used: true, children: childrenCount });
  });
  
  // Обработчики навигации
  btnNext.addEventListener('click', () => {
    if (selectedProgram !== null) {
      showStep(2);
    }
  });
  
  btnCalculate.addEventListener('click', () => {
    calculateAndShowResult();
  });
  
  btnBack.forEach(button => {
    button.addEventListener('click', () => {
      if (currentStep === 2) {
        showStep(1);
      } else if (currentStep === 3) {
        showStep(2);
      }
    });
  });
  
  btnConsultation.addEventListener('click', (e) => {
    e.preventDefault(); // Предотвращаем стандартное поведение ссылки
    
    // Плавная прокрутка к секции feedback-section
    const feedbackSection = document.getElementById('contacts');
    if (feedbackSection) {
      const header = document.querySelector('.site-header');
      const navHeight = header ? header.offsetHeight : 0;
      const targetPosition = feedbackSection.getBoundingClientRect().top + window.pageYOffset - navHeight - 20;
      
      window.scrollTo({
        top: targetPosition,
        behavior: 'smooth'
      });
    }
  });
  
  // Функции
  function updateCounterDisplay() {
    counterValue.textContent = childrenCount;
    minusBtn.disabled = childrenCount === 0;
  }
  
  function updateNextButton() {
    btnNext.disabled = selectedProgram === null;
  }
  
  function showStep(stepNumber) {
    // Скрываем все шаги
    document.querySelectorAll('.form-step').forEach(step => {
      step.classList.remove('active');
    });
    
    // Показываем нужный шаг
    document.querySelector(`.form-step[data-step="${stepNumber}"]`).classList.add('active');
    currentStep = stepNumber;
    
    // Обновляем сводку на шаге 3
    if (stepNumber === 3) {
      updateSummary();
    }
    
    // Скрываем результат при возврате к шагам 1 или 2
    if (stepNumber === 1 || stepNumber === 2) {
      hideResult();
    }
  }
  
  function hideResult() {
    resultPlaceholder.style.display = 'block';
    resultActive.style.display = 'none';
  }
  
  function updateSummary() {
    let programName = 'Обычная программа';
    if (selectedProgram === PRICE_BASE_REGULAR) {
      programName = 'Венгрия обычная';
    } else if (selectedProgram === PRICE_BASE_FAST) {
      programName = 'Венгрия ускоренная';
    } else if (selectedProgram === PRICE_BASE_POLAND) {
      programName = 'Польша за заслуги';
    }
    summaryProgram.textContent = programName;
    summaryChildren.textContent = childrenCount;
  }
  
  function calculateAndShowResult() {
    if (selectedProgram !== null && childrenCount >= 0) {
      const baseCost = selectedProgram;
      const childrenCost = childrenCount * PRICE_CHILD;
      const totalCost = baseCost + childrenCost;
      updateCalcSelectionPartial({ used: true, baseCost, children: childrenCount, childCostPer: PRICE_CHILD, total: totalCost });
      
      // Обновляем значения
      detailBase.textContent = `${baseCost} 000 €`;
      detailChildren.textContent = `${childrenCost} 000 €`;
      detailTotal.textContent = `${totalCost} 000 €`;
      resultAmount.textContent = totalCost;
      
      // Анимация прогресс-круга
      const circumference = 2 * Math.PI * 54;
      const offset = circumference - (totalCost / 350) * circumference;
      circleProgress.style.strokeDashoffset = offset;
      
      // Показываем активный результат
      resultPlaceholder.style.display = 'none';
      resultActive.style.display = 'block';
      
      // Переходим к шагу 3
      showStep(3);
    }
  }
});

// --- Reveal Up анимация при скролле ---
document.addEventListener('DOMContentLoaded', () => {
  // Элементы для анимации с разными эффектами
  const revealUpElements = document.querySelectorAll(
    '.challenge-item, .advantage-column, .program-option, .criteria-item'
  );
  
  const revealUpSoftElements = document.querySelectorAll(
    '.benefits-list li, .subtext-list li'
  );

  // Добавляем классы reveal up с задержками для создания каскадного эффекта
  revealUpElements.forEach((el, index) => {
    el.classList.add('reveal-up');
    
    // Добавляем задержки для создания волнового эффекта
    if (index % 3 === 1) el.classList.add('delay-1');
    if (index % 3 === 2) el.classList.add('delay-2');
  });

  // Мягкая анимация для текстовых элементов
  revealUpSoftElements.forEach((el, index) => {
    el.classList.add('reveal-up-soft');
    
    // Добавляем небольшие задержки
    if (index % 2 === 1) el.classList.add('delay-1');
  });

  const observer = new IntersectionObserver((entries, observer) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('visible');
        observer.unobserve(entry.target); // Анимация один раз
      }
    });
  }, {
    threshold: 0.15, // элемент должен быть виден на 15%
    rootMargin: '0px 0px -50px 0px' // анимация начинается немного раньше
  });

  // Наблюдаем за всеми элементами
  [...revealUpElements, ...revealUpSoftElements].forEach(el => observer.observe(el));
});

// --- РАСКРЫВАЮЩИЕСЯ КАРТОЧКИ ПРОБЛЕМ ---
document.addEventListener('DOMContentLoaded', () => {
  const toggleButtons = document.querySelectorAll('.toggle-arrow');
  
  toggleButtons.forEach(button => {
    button.addEventListener('click', function() {
      const card = this.closest('.challenge-item');
      const hiddenContent = card.querySelector('.hidden-content');
      
      // Переключаем классы активности
      hiddenContent.classList.toggle('active');
      this.classList.toggle('active');
      
      // Обновляем aria-атрибут для доступности
      const isExpanded = hiddenContent.classList.contains('active');
      this.setAttribute('aria-expanded', isExpanded);
      
      // Прокручиваем к карточке если она не полностью видна
      if (isExpanded) {
        const rect = card.getBoundingClientRect();
        if (rect.bottom > window.innerHeight || rect.top < 0) {
          card.scrollIntoView({ 
            behavior: 'smooth', 
            block: 'nearest' 
          });
        }
      }
    });
  });
  
  // Закрытие карточек при клике вне области
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.challenge-item.expandable')) {
      document.querySelectorAll('.hidden-content.active').forEach(content => {
        content.classList.remove('active');
      });
      document.querySelectorAll('.toggle-arrow.active').forEach(button => {
        button.classList.remove('active');
        button.setAttribute('aria-expanded', 'false');
      });
    }
  });
});

// Плавная прокрутка для стрелочки
document.addEventListener('DOMContentLoaded', () => {
  const scrollArrow = document.querySelector('.scroll-arrow');
  
  if (scrollArrow) {
    scrollArrow.addEventListener('click', function(e) {
      e.preventDefault();
      
      const targetId = this.getAttribute('href');
      const target = document.querySelector(targetId);
      
      if (target) {
        const header = document.querySelector('.site-header');
        const navHeight = header ? header.offsetHeight : 0;
        const targetPosition = target.getBoundingClientRect().top + window.pageYOffset - navHeight - 20;
        
        window.scrollTo({
          top: targetPosition,
          behavior: 'smooth'
        });
      }
    });
  }
});

(function(){
  fetch('/site_settings.json', {cache: 'no-store'}).then(function(res){
    if (!res.ok) throw new Error('no settings');
    return res.json();
  }).then(function(s){
    if (!s) return;

    // ========= PHONE =========
    if (s.phone) {
      var digitsForHref = s.phone.replace(/[^\d+]/g, '');
      // Обновляем href у всех ссылок, имеющих data-site="phone"
      document.querySelectorAll('[data-site="phone"]').forEach(function(el){
        try { el.setAttribute('href', 'tel:' + digitsForHref); } catch(e){}
      });
      // Обновляем текст в помеченных местах (не трогаем другие надписи)
      document.querySelectorAll('[data-site-display="phone"]').forEach(function(el){
        el.textContent = s.phone;
      });
      // Обновляем inline onclick с tel: если встречается
      document.querySelectorAll('[onclick]').forEach(function(b){
        var v = b.getAttribute('onclick');
        if (v && v.indexOf("tel:") !== -1) {
          b.setAttribute('onclick', "window.location.href='tel:" + digitsForHref + "'");
        }
      });
    }

    // ========= EMAIL =========
    if (s.email) {
      document.querySelectorAll('[data-site="email"]').forEach(function(el){
        try { el.setAttribute('href', 'mailto:' + s.email); } catch(e){}
      });
    }

    // ========= WHATSAPP =========
    if (s.whatsapp) {
      var waDigits = s.whatsapp.replace(/\D/g,'');
      if (waDigits.length) {
        document.querySelectorAll('[data-site="wa"]').forEach(function(el){
          try { el.setAttribute('href', 'https://wa.me/' + waDigits); } catch(e){}
        });
      }
    }

  }).catch(function(){ /* fallback: ничего не делаем, остаются статические значения */ });
})();

// Управление видимостью кнопок на основе настроек
class ButtonVisibilityManager {
  constructor() {
      this.settings = null;
      this.init();
  }

  async init() {
      try {
          // Загружаем настройки из JSON файла
          const response = await fetch('/site_settings.json');
          if (!response.ok) {
              throw new Error('Failed to load settings');
          }
          this.settings = await response.json();
          this.applyButtonVisibility();
      } catch (error) {
          console.warn('Could not load button visibility settings:', error);
          // Если файл не загрузился, показываем все кнопки по умолчанию
          this.showAllButtons();
      }
  }

  applyButtonVisibility() {
      if (!this.settings) return;

      const buttonsContainer = document.querySelector('.contact-buttons');
      if (!buttonsContainer) return;

      let visibleButtons = [];

      // Управление кнопкой телефона
      const phoneBtn = document.querySelector('.elegant-btn[data-button-type="phone"]');
      if (phoneBtn) {
          const isVisible = this.settings.show_phone_btn !== 0;
          phoneBtn.style.display = isVisible ? 'flex' : 'none';
          if (isVisible) visibleButtons.push(phoneBtn);
      }

      // Управление кнопкой email
      const emailBtn = document.querySelector('.elegant-btn[data-button-type="email"]');
      if (emailBtn) {
          const isVisible = this.settings.show_email_btn !== 0;
          emailBtn.style.display = isVisible ? 'flex' : 'none';
          if (isVisible) visibleButtons.push(emailBtn);
      }

      // Управление кнопкой WhatsApp
      const waBtn = document.querySelector('.elegant-btn[data-button-type="wa"]');
      if (waBtn) {
          const isVisible = this.settings.show_wa_btn !== 0;
          waBtn.style.display = isVisible ? 'flex' : 'none';
          if (isVisible) visibleButtons.push(waBtn);
      }

      // Обновляем классы контейнера в зависимости от количества видимых кнопок
      this.updateContainerLayout(visibleButtons.length);
  }

  updateContainerLayout(visibleCount) {
      const buttonsContainer = document.querySelector('.contact-buttons');
      if (!buttonsContainer) return;

      // Удаляем все существующие классы компоновки
      buttonsContainer.classList.remove('hidden', 'one-button', 'two-buttons', 'three-buttons');

      // Добавляем соответствующий класс в зависимости от количества видимых кнопок
      switch (visibleCount) {
          case 0:
              buttonsContainer.classList.add('hidden');
              break;
          case 1:
              buttonsContainer.classList.add('one-button');
              break;
          case 2:
              buttonsContainer.classList.add('two-buttons');
              break;
          case 3:
              buttonsContainer.classList.add('three-buttons');
              break;
      }
  }

  showAllButtons() {
      const buttons = document.querySelectorAll('.elegant-btn');
      buttons.forEach(btn => {
          btn.style.display = 'flex';
      });
      this.updateContainerLayout(3);
  }
}

// Инициализация менеджера видимости кнопок
document.addEventListener('DOMContentLoaded', function() {
  new ButtonVisibilityManager();

  // Также обновляем видимость при изменении настроек (если нужно в реальном времени)
  if (typeof window.updateButtonVisibility === 'undefined') {
      window.updateButtonVisibility = function() {
          new ButtonVisibilityManager();
      };
  }
});