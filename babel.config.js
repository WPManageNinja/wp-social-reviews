module.exports = {
    presets: [
      ['@babel/preset-env', {
        targets: {
          esmodules: true
        },
        useBuiltIns: false,
        modules: false
      }]
    ]
  }; 