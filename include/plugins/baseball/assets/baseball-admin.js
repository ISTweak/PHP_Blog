/* 試合日程・結果：管理画面の入力補助（素の JavaScript） */
(() => {
  'use strict';
  const form = document.querySelector('[data-bb-form]');
  if (!form) return;
  const radios = [...form.querySelectorAll('input[name="status"]')];
  const box = form.querySelector('[data-bb-scores]');
  const scores = [...box.querySelectorAll('input')];

  const sync = () => {
    const isFinal = form.querySelector('input[name="status"]:checked')?.value === 'final';
    box.classList.toggle('is-disabled', !isFinal);
    scores.forEach((i) => { i.required = isFinal; });
  };

  // 得点を入力したら「試合終了」にする
  scores.forEach((input) => input.addEventListener('input', () => {
    if (input.value !== '') {
      const fin = radios.find((r) => r.value === 'final');
      if (fin && !fin.checked) {
        fin.checked = true;
        sync();
      }
    }
  }));
  radios.forEach((r) => r.addEventListener('change', sync));
  sync();
})();
