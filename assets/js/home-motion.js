(function(){
  'use strict';
  var year=document.getElementById('home-year');
  if(year)year.textContent=String(new Date().getFullYear());

  var reduced=window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var reveals=document.querySelectorAll('[data-reveal]');
  if(reduced||!('IntersectionObserver' in window)){
    reveals.forEach(function(node){node.classList.add('is-visible');});
  }else{
    var observer=new IntersectionObserver(function(entries){
      entries.forEach(function(entry){
        if(entry.isIntersecting){entry.target.classList.add('is-visible');observer.unobserve(entry.target);}
      });
    },{threshold:.12,rootMargin:'0px 0px -7% 0px'});
    reveals.forEach(function(node){observer.observe(node);});

    var heroMedia=document.querySelector('.home-hero__media video');
    var ticking=false;
    function updateHero(){
      if(heroMedia){var offset=Math.min(window.scrollY*.055,42);heroMedia.style.transform='translate3d(0,'+offset+'px,0) scale(1.03)';}
      ticking=false;
    }
    window.addEventListener('scroll',function(){if(!ticking){window.requestAnimationFrame(updateHero);ticking=true;}},{passive:true});
    updateHero();
  }
}());
