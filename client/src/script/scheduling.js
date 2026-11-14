flatpickr("#calendar", {
    inline: true,
    locale: "pt",
    minDate: "today",
    maxDate: new Date().fp_incr(30), 
    disable: [
        date => date.getDay() === 0
    ],
});